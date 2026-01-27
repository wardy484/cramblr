<?php

use App\Enums\ExtractionJobStatus;
use App\Enums\JobPageStatus;
use App\Jobs\StartJobProcessing;
use App\Livewire\JobProgress;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('user can retry a failed extraction job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Failed,
        'progress_current' => 1,
        'progress_total' => 2,
        'error_message' => 'Failed',
    ]);

    JobPage::factory()->count(2)->for($job, 'job')->create([
        'status' => JobPageStatus::Failed,
        'error_message' => 'cURL error 18',
        'extraction_json' => ['items' => []],
        'raw_response' => ['foo' => 'bar'],
        'confidence' => 0.1,
    ]);

    $this->actingAs($user);

    Livewire::test(JobProgress::class, ['job' => $job])
        ->call('retry');

    $job->refresh();

    expect($job->status)->toBe(ExtractionJobStatus::Queued)
        ->and($job->progress_current)->toBe(0)
        ->and($job->progress_total)->toBe(3)
        ->and($job->error_message)->toBeNull();

    expect(JobPage::query()->where('job_id', $job->id)->where('status', JobPageStatus::Queued)->count())
        ->toBe(2);

    Queue::assertPushed(StartJobProcessing::class, function (StartJobProcessing $queuedJob) use ($job): bool {
        return $queuedJob->jobId === $job->id;
    });
});
