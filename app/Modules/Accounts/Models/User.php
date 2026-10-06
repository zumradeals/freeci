<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Catalog\Models\FreelanceProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_last_step'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'sandbox_payments' => 'boolean',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(AccountRole::class);
    }

    public function staffGrants(): HasMany
    {
        return $this->hasMany(StaffGrant::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    /** Administrateur = habilitation datée en vigueur (jamais un simple indicateur sur le compte). */
    public function isAdministrator(): bool
    {
        return $this->staffGrants()->active()->where('capability', StaffGrant::ADMINISTRATOR)->exists();
    }

    public function emailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** Double authentification activée = secret confirmé par un code valide (un secret seulement proposé ne compte pas). */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Assistance : habilitation « support » datée en vigueur (un administrateur en vigueur est aussi habilité à traiter l'assistance). */
    public function isSupport(): bool
    {
        return $this->staffGrants()->active()->where('capability', StaffGrant::SUPPORT)->exists();
    }

    public function isStaff(): bool
    {
        return $this->isAdministrator() || $this->isSupport();
    }

    public function freelanceProfile(): HasOne
    {
        return $this->hasOne(FreelanceProfile::class);
    }

    public function firstName(): string
    {
        return trim(explode(' ', trim($this->name))[0] ?? $this->name);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: '?';
    }
}
