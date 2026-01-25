<?php

namespace Database\Factories;

use App\Enums\ExtractionJobStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExtractionJob>
 */
class ExtractionJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'refinement_prompt' => fake()->optional()->sentence(),
            'status' => ExtractionJobStatus::Queued,
            'progress_current' => 0,
            'progress_total' => 0,
            'generation_json' => null,
            'generation_raw' => null,
            'error_message' => null,
        ];
    }
}
