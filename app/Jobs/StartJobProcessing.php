<?php

namespace App\Jobs;

use App\Enums\ExtractionJobStatus;
use App\Models\ExtractionJob;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

class StartJobProcessing implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public string $jobId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = ExtractionJob::query()
            ->with('pages')
            ->findOrFail($this->jobId);

        $job->update([
            'status' => ExtractionJobStatus::Processing,
            'progress_current' => 0,
            'progress_total' => $job->pages->count() + 1,
            'error_message' => null,
        ]);

        $pageJobs = $job->pages->map(fn ($page) => new ExtractPage($page->id))->all();

        Bus::batch($pageJobs)
            ->then(function (Batch $batch) use ($job): void {
                GenerateCardsFromJob::dispatch($job->id);
            })
            ->catch(function (Batch $batch, Throwable $exception) use ($job): void {
                $job->update([
                    'status' => ExtractionJobStatus::Failed,
                    'error_message' => $exception->getMessage(),
                ]);
            })
            ->dispatch();
    }
}
