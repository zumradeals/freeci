<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** Contrôles de dépendance (docs/02 §3.2) : l'interface ne lit ni n'écrit via les modèles. */
class ArchitectureTest extends TestCase
{
    public function test_interface_layer_does_not_use_eloquent_models_directly(): void
    {
        $allowed = ['app/Http/Controllers/Account/DashboardController.php'];
        $finder = (new Finder)->files()->in([app_path('Http'), app_path('Livewire')])->name('*.php');

        foreach ($finder as $file) {
            $rel = str_replace(base_path().'/', '', $file->getRealPath());
            if (in_array($rel, $allowed, true)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/Modules\\\\[A-Za-z]+\\\\Models\\\\/', $file->getContents(), "$rel importe un modèle");
        }
    }

    public function test_integrations_never_call_actions(): void
    {
        $dir = app_path('Integrations');
        if (! is_dir($dir)) {
            $this->addToAssertionCount(1);

            return;
        }
        foreach ((new Finder)->files()->in($dir)->name('*.php') as $file) {
            $this->assertStringNotContainsString('\\Actions\\', $file->getContents());
        }
    }
}
