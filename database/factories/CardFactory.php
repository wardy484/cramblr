<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'deck_id' => Deck::factory()->for($user),
            'status' => CardStatus::Proposed,
            'front' => fake()->sentence(),
            'back' => fake()->paragraph(),
            'tags' => [fake()->word(), fake()->word()],
            'extra' => [
                'source_text' => fake()->sentence(),
                'page_index' => 1,
                'notes' => fake()->optional()->sentence(),
                'confidence' => fake()->randomFloat(2, 0.2, 0.98),
            ],
            'source_job_id' => null,
        ];
    }
}
