<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_name'       => fake()->domainName(),
            'crawl_count'       => fake()->randomNumber(1, false),
            'last_crawled_at'   => new \DateTime(),
        ];
    }
}
