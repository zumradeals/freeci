<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\FreelanceProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FreelanceProfile> */
class FreelanceProfileFactory extends Factory
{
    protected $model = FreelanceProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'display_name' => fake()->name(),
            'headline' => 'Prestataire de test',
            'city' => 'Abidjan',
            'is_demo' => true,
        ];
    }
}
