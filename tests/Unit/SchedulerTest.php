<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Services\Study\Scheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('sm2 schedules a lapse on again', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'interval' => 10,
        'ease' => 2.5,
        'repetitions' => 3,
        'lapses' => 0,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'again', ['algorithm' => 'sm2']);

    expect($result['study_state'])->toBe('relearning')
        ->and($result['interval'])->toBe(1)
        ->and($result['lapses'])->toBe(1);
});

test('sm2 schedules growth on good', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'interval' => 6,
        'ease' => 2.5,
        'repetitions' => 2,
        'lapses' => 0,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'good', ['algorithm' => 'sm2']);

    expect($result['study_state'])->toBe('review')
        ->and($result['interval'])->toBeGreaterThan(6)
        ->and($result['repetitions'])->toBe(3);
});

test('learning step again resets to step 0', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 2,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'again', [
        'learning_steps_enabled' => true,
        'learning_steps' => ['1m', '10m', '1d'],
    ]);

    expect($result['is_learning'])->toBeTrue()
        ->and($result['learning_step_index'])->toBe(0)
        ->and($result['study_state'])->toBe('learning');
});

test('learning step good advances to next step', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 0,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'good', [
        'learning_steps_enabled' => true,
        'learning_steps' => ['1m', '10m', '1d'],
    ]);

    expect($result['is_learning'])->toBeTrue()
        ->and($result['learning_step_index'])->toBe(1)
        ->and($result['study_state'])->toBe('learning');
});

test('learning step good graduates when at final step', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 2,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'good', [
        'learning_steps_enabled' => true,
        'learning_steps' => ['1m', '10m', '1d'],
    ]);

    expect($result['is_learning'])->toBeFalse()
        ->and($result['learning_step_index'])->toBeNull()
        ->and($result['study_state'])->toBe('review');
});

test('learning step easy graduates immediately', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'learning',
        'is_learning' => true,
        'learning_step_index' => 0,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'easy', [
        'learning_steps_enabled' => true,
        'learning_steps' => ['1m', '10m', '1d'],
    ]);

    expect($result['is_learning'])->toBeFalse()
        ->and($result['learning_step_index'])->toBeNull()
        ->and($result['study_state'])->toBe('review')
        ->and($result['interval'])->toBe(4);
});

test('relearning step again resets to step 0', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'relearning',
        'is_relearning' => true,
        'learning_step_index' => 0,
        'lapses' => 1,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'again', [
        'learning_steps_enabled' => true,
        'relearning_steps' => ['10m'],
    ]);

    expect($result['is_relearning'])->toBeTrue()
        ->and($result['learning_step_index'])->toBe(0)
        ->and($result['study_state'])->toBe('relearning')
        ->and($result['lapses'])->toBe(2);
});

test('review card again enters relearning with learning steps enabled', function () {
    $card = Card::factory()->create([
        'status' => CardStatus::Approved,
        'study_state' => 'review',
        'is_learning' => false,
        'is_relearning' => false,
        'interval' => 10,
        'ease' => 2.5,
        'repetitions' => 3,
        'lapses' => 0,
    ]);

    $scheduler = new Scheduler();
    $result = $scheduler->scheduleReview($card, 'again', [
        'learning_steps_enabled' => true,
        'relearning_steps' => ['10m'],
    ]);

    expect($result['is_relearning'])->toBeTrue()
        ->and($result['learning_step_index'])->toBe(0)
        ->and($result['study_state'])->toBe('relearning');
});
