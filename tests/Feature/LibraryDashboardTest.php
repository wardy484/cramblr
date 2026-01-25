<?php

use App\Enums\CardStatus;
use App\Livewire\LibraryDashboard;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('users can select cards for mass deletion', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card1 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $card2 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->set('selectedCards', [$card1->id])
        ->assertSet('selectedCards', [$card1->id])
        ->set('selectedCards', [$card1->id, $card2->id])
        ->assertSet('selectedCards', [$card1->id, $card2->id]);
});

test('users can delete selected cards', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card1 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $card2 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $card3 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->set('selectedCards', [$card1->id, $card2->id])
        ->call('deleteSelected')
        ->assertSet('selectedCards', []);

    $this->assertDatabaseMissing('cards', ['id' => $card1->id]);
    $this->assertDatabaseMissing('cards', ['id' => $card2->id]);
    $this->assertDatabaseHas('cards', ['id' => $card3->id]);
});

test('deleting selected cards removes empty decks', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->set('selectedCards', [$card->id])
        ->call('deleteSelected')
        ->assertSet('selectedCards', []);

    $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    $this->assertDatabaseMissing('decks', ['id' => $deck->id]);
});

test('users can only delete their own cards', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $deck1 = Deck::factory()->for($user1)->create();
    $deck2 = Deck::factory()->for($user2)->create();
    $card1 = Card::factory()->for($user1)->for($deck1)->create(['status' => CardStatus::Approved]);
    $card2 = Card::factory()->for($user2)->for($deck2)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user1)
        ->test(LibraryDashboard::class)
        ->set('selectedCards', [$card1->id, $card2->id])
        ->call('deleteSelected');

    $this->assertDatabaseMissing('cards', ['id' => $card1->id]);
    $this->assertDatabaseHas('cards', ['id' => $card2->id]);
});

test('users can toggle select all cards on current page', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card1 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $card2 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->call('toggleSelectAll')
        ->assertSet('selectedCards', [$card1->id, $card2->id])
        ->call('toggleSelectAll')
        ->assertSet('selectedCards', []);
});

test('select all deselects only current page cards', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card1 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $card2 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $card3 = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->set('selectedCards', [$card1->id, $card2->id, $card3->id])
        ->call('toggleSelectAll');

    expect(Card::count())->toBe(3);
});

test('selected cards are cleared when filters change', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->set('selectedCards', [$card->id])
        ->set('search', 'test')
        ->assertSet('selectedCards', [])
        ->set('selectedCards', [$card->id])
        ->set('status', 'approved')
        ->assertSet('selectedCards', [])
        ->set('selectedCards', [$card->id])
        ->set('tag', 'test')
        ->assertSet('selectedCards', [])
        ->set('selectedCards', [$card->id])
        ->call('selectDeck', $deck->id)
        ->assertSet('selectedCards', []);
});

test('delete button appears when cards are selected', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->assertDontSee(__('Delete Selected'))
        ->set('selectedCards', [$card->id])
        ->assertSee(__('Delete Selected'));
});

test('delete selected does nothing when no cards are selected', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->call('deleteSelected');

    $this->assertDatabaseHas('cards', ['id' => $card->id]);
});

test('proposed cards are hidden by default', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $approvedCard = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $proposedCard = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Proposed]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->assertSee($approvedCard->front)
        ->assertDontSee($proposedCard->front);
});

test('proposed cards can be shown with toggle', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $approvedCard = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $proposedCard = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Proposed]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->assertDontSee($proposedCard->front)
        ->set('showProposed', true)
        ->assertSee($approvedCard->front)
        ->assertSee($proposedCard->front);
});

test('status filter overrides show proposed toggle', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $approvedCard = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);
    $proposedCard = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Proposed]);

    Livewire::actingAs($user)
        ->test(LibraryDashboard::class)
        ->set('status', 'proposed')
        ->assertSee($proposedCard->front)
        ->assertDontSee($approvedCard->front);
});
