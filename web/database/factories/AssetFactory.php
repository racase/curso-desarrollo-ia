<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CODE-#####??'),
            'name' => fake()->words(3, true),
            'site_id' => Site::factory(),
        ];
    }
}
