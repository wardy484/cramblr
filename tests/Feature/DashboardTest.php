<?php

use App\Enums\CardStatus;
use App\Livewire\Dashboard;
use App\Models\Card;
use App\Models\CardReview;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('dashboard recommends review when due cards exist', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Recommended: '.$deck->name)
        ->assertSee(__('Start review'));
});

test('dashboard recommends learning when only new cards exist', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'new',
        'due_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Recommended: '.$deck->name)
        ->assertSee(__('Start learn'));
});

test('dashboard avoids recommending the most recently reviewed deck when alternatives exist', function () {
    $user = User::factory()->create();
    $deckA = Deck::factory()->for($user)->create(['name' => 'Deck A']);
    $deckB = Deck::factory()->for($user)->create(['name' => 'Deck B']);
    $cardA = Card::factory()->for($user)->for($deckA)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);
    Card::factory()->for($user)->for($deckB)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->subDay(),
    ]);

    CardReview::factory()->for($user)->for($cardA)->create([
        'reviewed_at' => now()->subMinutes(30),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Recommended: '.$deckB->name)
        ->assertSee(__('Start review'));
});

test('dashboard shows recent history with recap action', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create(['name' => 'Recent Deck']);
    $card = Card::factory()->for($user)->for($deck)->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'due_at' => now()->addDay(),
        'last_reviewed_at' => now()->subHour(),
    ]);

    CardReview::factory()->for($user)->for($card)->create([
        'reviewed_at' => now()->subHour(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Recent Deck')
        ->assertSee(__('Recap'));
});