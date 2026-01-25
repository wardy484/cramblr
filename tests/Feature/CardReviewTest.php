<?php

use App\Enums\CardStatus;
use App\Enums\ExtractionJobStatus;
use App\Livewire\CardReview;
use App\Models\Card;
use App\Models\Deck;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Models\User;
use Livewire\Livewire;

test('rejecting selected cards deletes them', function () {
    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::NeedsReview,
    ]);

    JobPage::factory()->for($job, 'job')->create([
        'page_index' => 1,
    ]);

    $card1 = Card::factory()->for($user)->create([
        'status' => CardStatus::Proposed,
        'source_job_id' => $job->id,
        'extra' => [
            'source_text' => 'hello',
            'page_index' => 1,
            'confidence' => 0.9,
        ],
    ]);
    $card2 = Card::factory()->for($user)->create([
        'status' => CardStatus::Proposed,
        'source_job_id' => $job->id,
        'extra' => [
            'source_text' => 'thanks',
            'page_index' => 1,
            'confidence' => 0.8,
        ],
    ]);

    Livewire::actingAs($user)
        ->test(CardReview::class, ['job' => $job])
        ->set('selected', [$card1->id => true])
        ->call('rejectSelected')
        ->assertSet('selected', []);

    $this->assertDatabaseMissing('cards', ['id' => $card1->id]);
    $this->assertDatabaseHas('cards', ['id' => $card2->id]);
});

test('approving selected cards uses the chosen deck', function () {
    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::NeedsReview,
    ]);

    JobPage::factory()->for($job, 'job')->create([
        'page_index' => 1,
    ]);

    $deckOne = Deck::factory()->for($user)->create();
    $deckTwo = Deck::factory()->for($user)->create();

    $card = Card::factory()->for($user)->create([
        'status' => CardStatus::Proposed,
        'source_job_id' => $job->id,
        'deck_id' => $deckOne->id,
        'extra' => [
            'source_text' => 'hello',
            'page_index' => 1,
            'confidence' => 0.9,
        ],
    ]);

    Livewire::actingAs($user)
        ->test(CardReview::class, ['job' => $job])
        ->set('selected', [$card->id => true])
        ->set('cards.'.$card->id.'.deck_id', $deckTwo->id)
        ->call('approveSelected')
        ->assertSet('selected', []);

    $this->assertDatabaseHas('cards', [
        'id' => $card->id,
        'deck_id' => $deckTwo->id,
        'status' => CardStatus::Approved->value,
    ]);
});

test('rejecting selected cards removes empty decks', function () {
    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::NeedsReview,
    ]);

    JobPage::factory()->for($job, 'job')->create([
        'page_index' => 1,
    ]);

    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Proposed,
        'source_job_id' => $job->id,
        'extra' => [
            'source_text' => 'hello',
            'page_index' => 1,
            'confidence' => 0.9,
        ],
    ]);

    Livewire::actingAs($user)
        ->test(CardReview::class, ['job' => $job])
        ->set('selected', [$card->id => true])
        ->call('rejectSelected');

    $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    $this->assertDatabaseMissing('decks', ['id' => $deck->id]);
});
