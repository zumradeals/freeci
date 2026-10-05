<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_reproducible_idempotent_and_marked_as_demo(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [Category::count(), Service::count(), User::count()];
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [Category::count(), Service::count(), User::count()]);
        $this->assertSame(8, Category::count());
        $this->assertGreaterThan(12, Service::published()->count(), 'au moins deux pages de catalogue');
        $this->assertSame(0, Service::where('is_demo', false)->count());
        $this->assertSame(0, User::where('is_demo', false)->count());
        $this->assertSame(Service::count(), Service::query()->whereNotNull('slug')->count());
    }

    public function test_demo_seller_accounts_cannot_log_in_and_client_password_comes_from_config_or_is_random(): void
    {
        config(['freeci.demo_client_password' => 'Fourni-par-env-2026']);
        $this->seed(DatabaseSeeder::class);

        $client = User::where('email', 'client@demo.freeci.invalid')->firstOrFail();
        $this->assertTrue(\Hash::check('Fourni-par-env-2026', $client->password));

        $seller = User::where('email', 'kader@demo.freeci.invalid')->firstOrFail();
        foreach (['password', 'Password1', 'kader', 'demo'] as $guess) {
            $this->assertFalse(\Hash::check($guess, $seller->password));
        }
    }

    public function test_seed_refuses_to_run_in_production_unless_explicitly_allowed(): void
    {
        $this->app['env'] = 'production';
        config(['freeci.allow_demo_seed' => false]);
        (new DemoCatalogSeeder)->run();

        $this->assertSame(0, Service::count());
    }

    public function test_repository_contains_no_demo_password(): void
    {
        $seeder = file_get_contents(database_path('seeders/DemoCatalogSeeder.php'));
        $this->assertStringNotContainsString('Demo-Local', $seeder);
        $this->assertDoesNotMatchRegularExpression('/[\'"]password[\'"]\s*=>\s*[\'"][^\'"]+[\'"]/', $seeder);
    }
}
