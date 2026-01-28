<?php

use App\Enums\CardStatus;
use App\Livewire\LibraryDashboard;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('users can rename a deck', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create(['name' => 'Old name']);

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->call('openRenameDeckModal', $deck->id)
        ->assertSet('showRenameDeckModal', true)
        ->assertSet('deckToRename', $deck->id)
        ->assertSet('renameDeckName', 'Old name')
        ->set('renameDeckName', 'New name')
        ->call('renameDeck')
        ->assertSet('showRenameDeckModal', false);

    $this->assertDatabaseHas('decks', ['id' => $deck->id, 'name' => 'New name']);
});

test('users cannot rename another users deck', function () {
    $user = User::factory()->create();
    $otherDeck = Deck::factory()->create(['name' => 'Other deck']);

    $this->actingAs($user);

    expect(fn () => Livewire::test(LibraryDashboard::class)->call('openRenameDeckModal', $otherDeck->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('users can delete a deck and its cards', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create(['status' => CardStatus::Approved]);

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->call('openDeleteDeckModal', $deck->id)
        ->assertSet('showDeleteDeckModal', true)
        ->assertSet('deckToDelete', $deck->id)
        ->call('confirmDeleteDeck')
        ->assertSet('showDeleteDeckModal', false);

    $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    $this->assertDatabaseMissing('decks', ['id' => $deck->id]);
});

test('deleting a deck with children deletes descendants and their cards', function () {
    $user = User::factory()->create();
    $parent = Deck::factory()->for($user)->create();
    $child = Deck::factory()->for($user)->create(['parent_id' => $parent->id]);
    $parentCard = Card::factory()->for($user)->for($parent)->create();
    $childCard = Card::factory()->for($user)->for($child)->create();

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->call('openDeleteDeckModal', $parent->id)
        ->call('confirmDeleteDeck');

    $this->assertDatabaseMissing('decks', ['id' => $parent->id]);
    $this->assertDatabaseMissing('decks', ['id' => $child->id]);
    $this->assertDatabaseMissing('cards', ['id' => $parentCard->id]);
    $this->assertDatabaseMissing('cards', ['id' => $childCard->id]);
});

test('users cannot delete another users deck', function () {
    $user = User::factory()->create();
    $otherDeck = Deck::factory()->create();

    $this->actingAs($user);

    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    Livewire::test(LibraryDashboard::class)
        ->call('openDeleteDeckModal', $otherDeck->id);
});

test('deleting selected deck clears selected deck id', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->call('selectDeck', $deck->id)
        ->assertSet('selectedDeckId', $deck->id)
        ->call('openDeleteDeckModal', $deck->id)
        ->call('confirmDeleteDeck')
        ->assertSet('selectedDeckId', null);
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

test('deck badge reflects selected deck and can be cleared', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create(['name' => 'Spanish']);

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->assertSee('All decks')
        ->assertSee('aria-current="true"', false)
        ->call('selectDeck', $deck->id)
        ->assertSee('Spanish')
        ->assertSee('Clear')
        ->call('selectDeck', null)
        ->assertSee('All decks')
        ->assertSee('aria-current="true"', false);
});

test('users can open edit card modal and update card', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $otherDeck = Deck::factory()->for($user)->create(['name' => 'Other deck']);
    $card = Card::factory()->for($user)->for($deck)->create([
        'front' => 'Old front',
        'back' => 'Old back',
        'tags' => ['tag1', 'tag2'],
        'status' => CardStatus::Approved,
    ]);

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->call('openEditCardModal', $card->id)
        ->assertSet('showEditCardModal', true)
        ->assertSet('editingCardId', $card->id)
        ->assertSet('editCardFront', 'Old front')
        ->assertSet('editCardBack', 'Old back')
        ->assertSet('editCardTags', 'tag1, tag2')
        ->assertSet('editCardStatus', 'approved')
        ->assertSet('editCardDeckId', $deck->id)
        ->set('editCardFront', 'New front')
        ->set('editCardBack', 'New back')
        ->set('editCardTags', 'tag3, tag4')
        ->set('editCardStatus', 'archived')
        ->set('editCardDeckId', $otherDeck->id)
        ->call('saveCard')
        ->assertSet('showEditCardModal', false);

    $card->refresh();
    expect($card->front)->toBe('New front')
        ->and($card->back)->toBe('New back')
        ->and($card->tags)->toBe(['tag3', 'tag4'])
        ->and($card->status)->toBe(CardStatus::Archived)
        ->and($card->deck_id)->toBe($otherDeck->id);
});

test('users cannot edit another users card', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $deck = Deck::factory()->for($otherUser)->create();
    $card = Card::factory()->for($otherUser)->for($deck)->create();

    $this->actingAs($user);

    expect(fn () => Livewire::test(LibraryDashboard::class)->call('openEditCardModal', $card->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('users can remove card audio', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $path = 'cards/'.fake()->uuid().'/sample.mp3';
    Storage::disk('private')->put($path, 'audio content');
    $card = Card::factory()->for($user)->for($deck)->create(['audio_path' => $path]);

    $this->actingAs($user);

    Livewire::test(LibraryDashboard::class)
        ->call('openEditCardModal', $card->id)
        ->call('removeCardAudio', $card->id);

    $card->refresh();
    expect($card->audio_path)->toBeNull();
    Storage::disk('private')->assertMissing($path);
});

test('users can add audio to card via edit modal', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $card = Card::factory()->for($user)->for($deck)->create(['audio_path' => null]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('pronunciation.mp3', 100, 'audio/mpeg');

    Livewire::test(LibraryDashboard::class)
        ->call('openEditCardModal', $card->id)
        ->set('editCardAudio', $file)
        ->call('saveCard');

    $card->refresh();
    expect($card->audio_path)->not->toBeNull()
        ->and($card->audio_path)->toContain('cards/'.$card->id.'/');
    Storage::disk('private')->assertExists($card->audio_path);
});

test('users cannot replace audio without removing it first', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $existingPath = 'cards/'.$deck->id.'/existing.mp3';
    Storage::disk('private')->put($existingPath, 'audio content');
    $card = Card::factory()->for($user)->for($deck)->create(['audio_path' => $existingPath]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('replacement.mp3', 100, 'audio/mpeg');

    Livewire::test(LibraryDashboard::class)
        ->call('openEditCardModal', $card->id)
        ->set('editCardAudio', $file)
        ->call('saveCard')
        ->assertHasErrors(['editCardAudio']);

    $card->refresh();
    expect($card->audio_path)->toBe($existingPath);
    Storage::disk('private')->assertExists($existingPath);
});
