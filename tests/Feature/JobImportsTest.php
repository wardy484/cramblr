<?php

use App\Enums\ExtractionJobStatus;
use App\Livewire\JobImports;
use App\Models\ExtractionJob;
use App\Models\User;
use Livewire\Livewire;

test('user sees active imports by default', function () {
    $user = User::factory()->create();

    $queued = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Queued,
    ]);
    $processing = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Processing,
    ]);
    $needsReview = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::NeedsReview,
    ]);
    $completed = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Completed,
    ]);
    $otherUserJob = ExtractionJob::factory()->create([
        'status' => ExtractionJobStatus::Queued,
    ]);

    $this->actingAs($user);

    Livewire::test(JobImports::class)
        ->assertSee($queued->id)
        ->assertSee($processing->id)
        ->assertSee($needsReview->id)
        ->assertDontSee($completed->id)
        ->assertDontSee($otherUserJob->id);
});

test('user can view all imports when filter is set', function () {
    $user = User::factory()->create();

    $completed = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Completed,
    ]);

    $this->actingAs($user);

    Livewire::test(JobImports::class)
        ->set('status', 'all')
        ->assertSee($completed->id);
});
