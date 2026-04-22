<?php

namespace Database\Factories;

use App\Models\JobLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobLead>
 */
class JobLeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'source_name' => fake()->randomElement(['LinkedIn', 'Indeed', 'Company Site']),
            'source_url' => fake()->url(),
            'location' => fake()->optional()->city(),
            'work_mode' => fake()->optional()->randomElement(JobLead::workModes()),
            'salary_range' => fake()->optional()->randomElement(['$90k-$110k', '$120k-$150k']),
            'description_excerpt' => fake()->optional()->paragraph(),
            'relevance_score' => fake()->optional()->numberBetween(40, 95),
            'lead_status' => fake()->randomElement(JobLead::leadStatuses()),
            'discovered_at' => fake()->optional()->date(),
        ];
    }
}
