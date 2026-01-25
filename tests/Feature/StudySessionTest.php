<?php

use App\Enums\CardStatus;
use App\Livewire\StudySession;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Livewire\Livewire;

test('study session loads due cards for a deck', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->assertSet('currentCardId', $card->id);
});

test('study session shows answer button before ratings', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->assertSee(__('Show answer'))
        ->assertDontSee(__('Again'))
        ->call('flip')
        ->assertSee(__('Again'));
});

test('rating a card records a review and schedules a due date', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'good');

    $this->assertDatabaseHas('cards', [
        'id' => $card->id,
        'study_state' => 'review',
    ]);

    $this->assertDatabaseHas('card_reviews', [
        'card_id' => $card->id,
        'rating' => 'good',
    ]);
});

test('rating hard requeues the card for this session', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDays(2),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'hard');

    $queue = $component->get('queue');

    expect($queue[0]['card_id'])->toBe($card2->id)
        ->and($queue[1]['card_id'])->toBe($card1->id);
});

test('rating good requeues the card for this session', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDays(2),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'good');

    $queue = $component->get('queue');

    expect($queue[0]['card_id'])->toBe($card2->id)
        ->and($queue[1]['card_id'])->toBe($card1->id);
});

test('extend session appends more due cards', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create([
        'study_settings' => [
            'max_reviews_per_session' => 1,
        ],
    ]);
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDays(2),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->assertSet('queue.0.card_id', $card1->id)
        ->call('extendSession')
        ->assertSet('queue.1.card_id', $card2->id);
});

test('learning step again requeues immediately when delay is 0', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create([
        'study_settings' => [
            'learning_steps_enabled' => true,
            'learning_steps' => ['1m', '10m', '1d'],
            'again_delay_cards' => 0,
        ],
    ]);
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 1,
        'due_at' => now()->subMinute(),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'again');

    $queue = $component->get('queue');
    expect($queue[0]['card_id'])->toBe($card1->id);
});

test('learning step again requeues after delay cards when delay > 0', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create([
        'study_settings' => [
            'learning_steps_enabled' => true,
            'learning_steps' => ['1m', '10m', '1d'],
            'again_delay_cards' => 2,
        ],
    ]);
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 1,
        'due_at' => now()->subMinute(),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);
    $card3 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'again');

    $queue = $component->get('queue');
    expect($queue[2]['card_id'])->toBe($card1->id);
});

test('learning step good requeues when not graduating', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create([
        'study_settings' => [
            'learning_steps_enabled' => true,
            'learning_steps' => ['1m', '10m', '1d'],
        ],
    ]);
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 0,
        'due_at' => now()->subMinute(),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'good');

    $queue = $component->get('queue');
    expect($queue)->toHaveCount(2)
        ->and($queue[1]['card_id'])->toBe($card1->id);
});

test('learning step good does not requeue when graduating', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create([
        'study_settings' => [
            'learning_steps_enabled' => true,
            'learning_steps' => ['1m', '10m', '1d'],
        ],
    ]);
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 2,
        'due_at' => now()->subMinute(),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'good');

    $queue = $component->get('queue');
    expect($queue)->toHaveCount(1)
        ->and($queue[0]['card_id'])->toBe($card2->id);

    $this->assertDatabaseHas('cards', [
        'id' => $card1->id,
        'study_state' => 'review',
        'is_learning' => false,
    ]);
});

test('learning step easy does not requeue', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create([
        'study_settings' => [
            'learning_steps_enabled' => true,
            'learning_steps' => ['1m', '10m', '1d'],
        ],
    ]);
    $card1 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 0,
        'due_at' => now()->subMinute(),
    ]);
    $card2 = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck])
        ->call('rate', 'easy');

    $queue = $component->get('queue');
    expect($queue)->toHaveCount(1)
        ->and($queue[0]['card_id'])->toBe($card2->id);

    $this->assertDatabaseHas('cards', [
        'id' => $card1->id,
        'study_state' => 'review',
        'is_learning' => false,
    ]);
});

test('recap mode loads recently reviewed cards even if not due', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->addDays(3),
        'last_reviewed_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(StudySession::class, ['deck' => $deck, 'recap' => true])
        ->assertSet('currentCardId', $card->id)
        ->assertSee(__('Recap'));
});
