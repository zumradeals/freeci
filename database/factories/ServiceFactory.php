<?php

namespace Database\Factories;

use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $title = 'Service '.fake()->unique()->words(3, true);

        return [
            'category_id' => CategoryFactory::new(),
            'freelance_profile_id' => FreelanceProfileFactory::new(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'title' => $title,
            'summary' => 'Résumé du service de test.',
            'scope' => 'Périmètre du service de test.',
            'price_xof' => 25000,
            'delivery_days' => 5,
            'revisions_included' => 2,
            'deliverables' => ['Un livrable'],
            'exclusions' => ['Une exclusion'],
            'client_inputs' => ['Un élément à fournir'],
            'images' => [],
            'status' => ServiceStatus::Published->value,
            'published_at' => now()->subDay(),
            'is_demo' => true,
        ];
    }

    public function status(ServiceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDay()]);
    }
}
