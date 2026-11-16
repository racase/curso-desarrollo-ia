<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CODE-#####??'),
            'name' => fake()->words(3, true),
        ];
    }
}
