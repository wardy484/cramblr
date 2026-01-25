<?php

namespace Database\Factories;

use App\Enums\ExportStatus;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Export>
 */
class ExportFactory extends Factory
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
            'status' => ExportStatus::Queued,
            'apkg_path' => null,
            'error_message' => null,
        ];
    }
}
