<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return ['site_id' => Site::factory(), 'code' => fake()->unique()->bothify('ASSET-????????'), 'name' => fake()->word()];
    }
}
