<?php

namespace Database\Seeders;

use App\Enums\CardStatus;
use App\Enums\ExtractionJobStatus;
use App\Enums\JobPageStatus;
use App\Models\Card;
use App\Models\Deck;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Models\User;
use Illuminate\Database\Seeder;

class FlashcardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create();

        $deck = Deck::factory()
            ->for($user)
            ->create(['name' => 'Sample Deck']);

        Card::factory()
            ->count(10)
            ->for($user)
            ->for($deck)
            ->state(['status' => CardStatus::Approved])
            ->create();

        $job = ExtractionJob::factory()
            ->for($user)
            ->state([
                'status' => ExtractionJobStatus::NeedsReview,
                'progress_current' => 2,
                'progress_total' => 2,
            ])
            ->create();

        JobPage::factory()
            ->count(2)
            ->for($job, 'job')
            ->state(['status' => JobPageStatus::Extracted])
            ->create();
    }
}
