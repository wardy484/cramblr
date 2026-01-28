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

test('user can clear pending imports', function () {
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
    $failed = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Failed,
    ]);
    $otherUserJob = ExtractionJob::factory()->create([
        'status' => ExtractionJobStatus::Queued,
    ]);

    $this->actingAs($user);

    Livewire::test(JobImports::class)
        ->call('clearPendingImports');

    $this->assertDatabaseMissing('extraction_jobs', ['id' => $queued->id]);
    $this->assertDatabaseMissing('extraction_jobs', ['id' => $processing->id]);
    $this->assertDatabaseHas('extraction_jobs', ['id' => $needsReview->id]);
    $this->assertDatabaseHas('extraction_jobs', ['id' => $failed->id]);
    $this->assertDatabaseHas('extraction_jobs', ['id' => $otherUserJob->id]);
});

test('user can delete an import', function () {
    $user = User::factory()->create();

    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Queued,
    ]);

    $this->actingAs($user);

    Livewire::test(JobImports::class)
        ->call('deleteImport', $job->id);

    $this->assertDatabaseMissing('extraction_jobs', ['id' => $job->id]);
});

test('user cannot delete another users import', function () {
    $user = User::factory()->create();

    $otherUserJob = ExtractionJob::factory()->create([
        'status' => ExtractionJobStatus::Queued,
    ]);

    $this->actingAs($user);

    expect(fn () => Livewire::test(JobImports::class)->call('deleteImport', $otherUserJob->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $this->assertDatabaseHas('extraction_jobs', ['id' => $otherUserJob->id]);
});
