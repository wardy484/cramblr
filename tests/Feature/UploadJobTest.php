<?php

use App\Jobs\StartJobProcessing;
use App\Livewire\UploadJob;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('user can create an extraction job from images', function () {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(UploadJob::class)
        ->set('images', [
            UploadedFile::fake()->image('page1.jpg'),
            UploadedFile::fake()->image('page2.jpg'),
        ])
        ->set('refinementPrompt', 'Focus on vocab.')
        ->call('submit')
        ->assertRedirect();

    $extractionJob = ExtractionJob::query()->first();

    expect($extractionJob)->not->toBeNull()
        ->and($extractionJob->user_id)->toBe($user->id)
        ->and($extractionJob->progress_total)->toBe(2);

    expect(JobPage::query()->count())->toBe(2);

    Queue::assertPushed(StartJobProcessing::class, function (StartJobProcessing $queuedJob) use ($extractionJob): bool {
        return $queuedJob->jobId === $extractionJob->id;
    });
});
