<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CardReview>
 */
class CardReviewFactory extends Factory
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
            'card_id' => Card::factory()->for($user),
            'rating' => fake()->randomElement(['again', 'hard', 'good', 'easy']),
            'interval' => fake()->numberBetween(1, 30),
            'ease' => fake()->randomFloat(2, 1.3, 3.0),
            'reviewed_at' => now(),
            'algorithm' => fake()->randomElement(['sm2', 'fsrs']),
            'due_at' => now()->addDays(fake()->numberBetween(1, 30)),
            'data' => [
                'algorithm_used' => 'sm2',
            ],
        ];
    }
}
