<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceUnavailablePageTest extends TestCase
{
    public function test_the_maintenance_page_is_in_french_and_reassures_without_technical_wording(): void
    {
        $html = view('errors.503')->render();
        $this->assertStringContainsString('Service momentanément indisponible', $html);
        $this->assertStringContainsString('aucune commande ni aucun paiement n’est perdu', $html);
        $this->assertStringNotContainsString('Service Unavailable', $html);
    }

    public function test_the_livewire_scripts_ignore_transient_server_unavailability(): void
    {
        $js = file_get_contents(base_path('resources/js/livewire-resilience.js'));
        foreach ([502, 503, 504] as $status) {
            $this->assertStringContainsString((string) $status, $js);
        }
        $this->assertStringContainsString("import './livewire-resilience.js';", file_get_contents(base_path('resources/js/app.js')));
    }
}
