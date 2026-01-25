<?php

use App\Enums\CardStatus;
use App\Livewire\LearnSession;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Livewire\Livewire;

test('learn session loads new cards for a deck', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'new',
    ]);

    Livewire::actingAs($user)
        ->test(LearnSession::class, ['deck' => $deck])
        ->assertSet('currentCardId', $card->id);
});

test('marking a card learned schedules a review', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'new',
    ]);

    Livewire::actingAs($user)
        ->test(LearnSession::class, ['deck' => $deck])
        ->call('markLearned');

    $this->assertDatabaseHas('cards', [
        'id' => $card->id,
        'study_state' => 'review',
    ]);

    $this->assertDatabaseHas('card_reviews', [
        'card_id' => $card->id,
        'rating' => 'good',
    ]);
});
