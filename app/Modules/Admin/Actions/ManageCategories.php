<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestion des catégories depuis l'administration. Toute action est journalisée (AdminAudit) ; rien n'est supprimé tant qu'un service ou une mission l'utilise
 * (l'archivage retire la catégorie des choix sans toucher à l'historique). L'adresse (slug) est figée à la création pour ne casser aucun lien.
 */
final class ManageCategories
{
    public const ICONS = [
        'building' => 'Bâtiment', 'cog' => 'Engrenage', 'code' => 'Code', 'pen' => 'Crayon', 'camera' => 'Appareil photo', 'megaphone' => 'Porte-voix',
        'text' => 'Texte', 'cap' => 'Formation', 'globe' => 'Globe', 'briefcase' => 'Mallette', 'package' => 'Colis', 'clipboard' => 'Dossier',
        'calendar' => 'Calendrier', 'shield' => 'Bouclier', 'pin' => 'Lieu', 'list' => 'Liste',
    ];

    public function __construct(private AdminAudit $audit) {}

    /** @return list<array<string, mixed>> */
    public function overview(): array
    {
        $services = DB::table('services')->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        $published = DB::table('services')->whereNotNull('published_at')->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        $missions = DB::table('mission_versions')->selectRaw('category_id, count(distinct mission_id) as n')->groupBy('category_id')->pluck('n', 'category_id');

        return Category::query()->orderByRaw('archived_at is not null')->orderBy('position')->get()->map(fn (Category $c) => [
            'id' => $c->getKey(), 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon, 'archived' => $c->archived_at !== null,
            'services' => (int) ($services[$c->getKey()] ?? 0), 'published' => (int) ($published[$c->getKey()] ?? 0), 'missions' => (int) ($missions[$c->getKey()] ?? 0),
        ])->all();
    }

    public function create(User $admin, string $name, string $icon): Category
    {
        $name = $this->cleanName($name);
        $this->assertIcon($icon);

        return $this->audit->run($admin, 'category.create', 'category', null, $name, null, function () use ($name, $icon) {
            $this->assertNameFree($name, null);
            $slug = $base = Str::slug($name) ?: 'categorie';
            for ($i = 2; Category::query()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }

            return Category::query()->create(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'position' => ((int) Category::query()->max('position')) + 1]);
        });
    }

    public function update(User $admin, string $id, string $name, string $icon): void
    {
        $name = $this->cleanName($name);
        $this->assertIcon($icon);
        $category = $this->find($id);
        $this->audit->run($admin, 'category.update', 'category', $id, $name, null, function () use ($category, $name, $icon) {
            $this->assertNameFree($name, $category->getKey());
            $category->update(['name' => $name, 'icon' => $icon]);
        });
    }

    /** Échange la position avec la voisine (parmi les catégories de même état). */
    public function move(User $admin, string $id, string $direction): void
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $category = $this->find($id);
        $this->audit->run($admin, 'category.move', 'category', $id, $category->name, null, function () use ($category, $direction) {
            $neighbour = Category::query()->whereNull('archived_at')
                ->when($direction === 'up', fn ($q) => $q->where('position', '<', $category->position)->orderByDesc('position'), fn ($q) => $q->where('position', '>', $category->position)->orderBy('position'))->first();
            if ($neighbour === null) {
                return;
            }
            DB::transaction(function () use ($category, $neighbour) {
                [$a, $b] = [$category->position, $neighbour->position];
                $category->update(['position' => $b]);
                $neighbour->update(['position' => $a]);
            });
        });
    }

    public function archive(User $admin, string $id, string $reason): void
    {
        $category = $this->find($id);
        $this->audit->run($admin, 'category.archive', 'category', $id, $category->name, $reason, function () use ($category) {
            if ($category->archived_at !== null) {
                return;
            }
            if (Category::query()->active()->count() <= 1) {
                throw new ModerationDenied('Au moins une catégorie doit rester proposée : sans catégorie, aucun service ni mission ne peut être créé.');
            }
            $category->update(['archived_at' => now()]);
        });
    }

    public function restore(User $admin, string $id): void
    {
        $category = $this->find($id);
        $this->audit->run($admin, 'category.restore', 'category', $id, $category->name, null, function () use ($category) {
            $category->update(['archived_at' => null, 'position' => ((int) Category::query()->max('position')) + 1]);
        });
    }

    /** Suppression définitive : seulement si aucun service ni aucune mission (même en brouillon) ne l'utilise. */
    public function delete(User $admin, string $id, string $reason): void
    {
        $category = $this->find($id);
        $this->audit->run($admin, 'category.delete', 'category', $id, $category->name, $reason, function () use ($category) {
            $used = DB::table('services')->where('category_id', $category->getKey())->exists()
                || DB::table('service_versions')->where('category_id', $category->getKey())->exists()
                || DB::table('mission_versions')->where('category_id', $category->getKey())->exists();
            if ($used) {
                throw new ModerationDenied('Cette catégorie est utilisée par des services ou des missions : archivez-la plutôt que de la supprimer.');
            }
            $category->delete();
        });
    }

    private function find(string $id): Category
    {
        return Category::query()->findOrFail($id);
    }

    private function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
            throw new ModerationDenied('Le nom d’une catégorie doit faire entre 3 et 60 caractères.');
        }

        return $name;
    }

    private function assertIcon(string $icon): void
    {
        if (! isset(self::ICONS[$icon])) {
            throw new ModerationDenied('Choisissez une icône dans la liste.');
        }
    }

    private function assertNameFree(string $name, ?string $exceptId): void
    {
        $taken = Category::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists();
        if ($taken) {
            throw new ModerationDenied('Une catégorie porte déjà ce nom.');
        }
    }
}
