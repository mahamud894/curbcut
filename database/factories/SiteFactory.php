<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'url' => 'https://example.test/',
            'sitemap_url' => null,
            'max_pages' => 20,
            'scan_frequency' => 'weekly',
        ];
    }
}
