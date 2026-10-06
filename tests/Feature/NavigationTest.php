<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Garde-fou : la navigation ne doit jamais laisser apparaître une directive Blade brute (ex. « word@livewire » non compilé). */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_space_navigation_renders_its_unread_badges_instead_of_raw_directives(): void
    {
        $u = User::factory()->create();
        $u->roles()->firstOrCreate(['role' => 'client']);
        foreach (['/espace', '/espace/messages', '/espace/notifications', '/espace/assistance'] as $url) {
            $html = $this->actingAs($u)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('@livewire(', $html, $url);
            $this->assertStringNotContainsString('@if(', $html, $url);
            $this->assertStringContainsString('wire:name="unread-badge"', $html, $url);
        }
    }
}
