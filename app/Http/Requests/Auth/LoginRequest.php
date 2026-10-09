<?php

namespace App\Http\Requests\Auth;

use App\Modules\Accounts\Security\LoginSecondStep;
use App\Modules\Accounts\Security\SecurityLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * Une seule erreur générique : on ne révèle pas si l'adresse existe.
     *
     * @return bool vrai si une DEUXIÈME ÉTAPE est requise : la session n'est alors PAS ouverte (étape en attente, aucune connexion mémorisée)
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => mb_strtolower($this->string('email')->toString()), 'password' => $this->string('password')->toString()];
        $guard = Auth::guard('web');
        $provider = $guard->getProvider();
        $user = $provider->retrieveByCredentials($credentials);
        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($this->throttleKey(), 300);
            SecurityLog::record('login_failed', DB::table('users')->where('email', $credentials['email'])->value('id'));      // ni mot de passe ni adresse saisie

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
        if ($user->hasTwoFactor()) {
            app(LoginSecondStep::class)->begin($this->session(), $user->getAuthIdentifier());

            return true;
        }
        $guard->login($user, $this->boolean('remember'));

        return false;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        SecurityLog::record('login_locked', DB::table('users')->where('email', mb_strtolower($this->string('email')->toString()))->value('id'));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
