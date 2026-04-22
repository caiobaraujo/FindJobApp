<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'source_url' => fake()->optional()->url(),
            'status' => fake()->randomElement(Application::statuses()),
            'applied_at' => fake()->optional()->date(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
