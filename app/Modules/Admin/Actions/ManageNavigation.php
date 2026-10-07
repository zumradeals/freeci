<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Navigation\Destinations;
use App\Modules\Admin\Navigation\MenuItems;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Support\Facades\DB;

/**
 * Menus du site gérés depuis l'administration. Chaque action est journalisée ; la destination est choisie dans la liste fermée des pages du site.
 * « Personnaliser » copie les liens d'origine ; « Rétablir » supprime la personnalisation de la zone (retour aux liens d'origine).
 */
final class ManageNavigation
{
    public function __construct(private AdminAudit $audit) {}

    public function customize(User $admin, string $area): void
    {
        $this->assertArea($area);
        $this->audit->run($admin, 'menu.customize', 'menu', $area, MenuItems::AREAS[$area], null, function () use ($area) {
            if (DB::table('menu_items')->where('area', $area)->exists()) {
                return;
            }
            foreach (MenuItems::defaults($area) as $i => $d) {
                DB::table('menu_items')->insert(['area' => $area, 'label' => $d['label'], 'destination' => $d['destination'], 'position' => $i + 1, 'visible' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            MenuItems::forget();
        });
    }

    public function add(User $admin, string $area, string $label, string $destination): void
    {
        $this->assertArea($area);
        [$label, $destination] = [$this->label($label), $this->destination($destination)];
        $this->audit->run($admin, 'menu.add', 'menu', $area, $label, null, function () use ($area, $label, $destination) {
            $this->assertCustomized($area);
            if (DB::table('menu_items')->where('area', $area)->count() >= MenuItems::MAX[$area]) {
                throw new ModerationDenied('Cette zone ne peut pas contenir plus de '.MenuItems::MAX[$area].' liens.');
            }
            DB::table('menu_items')->insert(['area' => $area, 'label' => $label, 'destination' => $destination, 'position' => ((int) DB::table('menu_items')->where('area', $area)->max('position')) + 1, 'visible' => true, 'created_at' => now(), 'updated_at' => now()]);
            MenuItems::forget();
        });
    }

    public function update(User $admin, int $id, string $label, string $destination, bool $visible): void
    {
        [$label, $destination] = [$this->label($label), $this->destination($destination)];
        $row = $this->find($id);
        $this->audit->run($admin, 'menu.update', 'menu', (string) $id, $label, null, function () use ($row, $label, $destination, $visible) {
            if (! $visible) {
                $this->assertStillVisible($row->area, $row->id);
            }
            DB::table('menu_items')->where('id', $row->id)->update(['label' => $label, 'destination' => $destination, 'visible' => $visible, 'updated_at' => now()]);
            MenuItems::forget();
        });
    }

    public function move(User $admin, int $id, string $direction): void
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $row = $this->find($id);
        $this->audit->run($admin, 'menu.move', 'menu', (string) $id, $row->label, null, function () use ($row, $direction) {
            $items = DB::table('menu_items')->where('area', $row->area)->orderBy('position')->orderBy('id')->get()->values();
            $i = $items->search(fn ($r) => $r->id === $row->id);
            $j = $direction === 'up' ? $i - 1 : $i + 1;
            if ($i === false || $j < 0 || $j >= $items->count()) {
                return;
            }
            $ordered = $items->all();
            [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
            foreach ($ordered as $pos => $r) {
                DB::table('menu_items')->where('id', $r->id)->update(['position' => $pos + 1]);
            }
            MenuItems::forget();
        });
    }

    public function delete(User $admin, int $id): void
    {
        $row = $this->find($id);
        $this->audit->run($admin, 'menu.delete', 'menu', (string) $id, $row->label, null, function () use ($row) {
            $this->assertStillVisible($row->area, $row->id);
            DB::table('menu_items')->where('id', $row->id)->delete();
            MenuItems::forget();
        });
    }

    public function reset(User $admin, string $area): void
    {
        $this->assertArea($area);
        $this->audit->run($admin, 'menu.reset', 'menu', $area, MenuItems::AREAS[$area], null, function () use ($area) {
            DB::table('menu_items')->where('area', $area)->delete();
            MenuItems::forget();
        });
    }

    private function assertArea(string $area): void
    {
        if (! isset(MenuItems::AREAS[$area])) {
            throw new ModerationDenied('Zone de menu inconnue.');
        }
    }

    private function assertCustomized(string $area): void
    {
        if (! DB::table('menu_items')->where('area', $area)->exists()) {
            throw new ModerationDenied('Personnalisez d’abord ce menu.');
        }
    }

    /** Le menu principal doit toujours garder au moins un lien visible. */
    private function assertStillVisible(string $area, int $exceptId): void
    {
        if ($area === 'header' && ! DB::table('menu_items')->where('area', 'header')->where('visible', true)->where('id', '!=', $exceptId)->exists()) {
            throw new ModerationDenied('Le menu principal doit garder au moins un lien visible.');
        }
    }

    private function label(string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        if (mb_strlen($label) < 2 || mb_strlen($label) > 40) {
            throw new ModerationDenied('Le texte d’un lien doit faire entre 2 et 40 caractères.');
        }

        return $label;
    }

    private function destination(string $destination): string
    {
        if (! isset(Destinations::options()[$destination])) {
            throw new ModerationDenied('Choisissez une page dans la liste.');
        }

        return $destination;
    }

    private function find(int $id): object
    {
        return DB::table('menu_items')->where('id', $id)->first() ?? throw new ModerationDenied('Lien introuvable.');
    }
}
