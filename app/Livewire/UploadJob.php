<?php

namespace App\Livewire;

use App\Enums\ExtractionJobStatus;
use App\Enums\JobPageStatus;
use App\Jobs\StartJobProcessing;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class UploadJob extends Component
{
    use WithFileUploads;

    /**
     * @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile>
     */
    public array $images = [];

    public ?string $refinementPrompt = null;

    public string $translationPreference = 'phonetic';

    public bool $generateAudio = true;

    public bool $isProcessing = false;

    public function removeImage(int $index): void
    {
        if (isset($this->images[$index])) {
            $this->images[$index]->delete();
            unset($this->images[$index]);
            $this->images = array_values($this->images);
        }
    }

    public function render(): View
    {
        return view('livewire.upload-job')
            ->layout('layouts.app', ['title' => __('Create from images')]);
    }

    public function submit(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->isProcessing = true;

        $throttleKey = 'jobs:create:'.Auth::id();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('images', __('Please wait before creating another job.'));
            $this->isProcessing = false;

            return;
        }

        $validated = $this->validate([
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['image', 'max:10240'],
            'refinementPrompt' => ['nullable', 'string', 'max:2000'],
            'translationPreference' => ['required', 'in:phonetic,thai'],
            'generateAudio' => ['boolean'],
        ]);

        RateLimiter::hit($throttleKey, 60);

        $job = ExtractionJob::query()->create([
            'user_id' => Auth::id(),
            'refinement_prompt' => $validated['refinementPrompt'],
            'translation_preference' => $validated['translationPreference'],
            'import_audio' => $validated['generateAudio'] ?? true,
            'status' => ExtractionJobStatus::Queued,
            'progress_current' => 0,
            'progress_total' => count($validated['images']),
        ]);

        $pages = [];

        foreach ($validated['images'] as $index => $image) {
            $path = $image->storeAs(
                'jobs/'.$job->id,
                sprintf('page_%s_%s.%s', $index + 1, Str::uuid(), $image->getClientOriginalExtension()),
                'private'
            );

            $pages[] = [
                'id' => Str::uuid()->toString(),
                'job_id' => $job->id,
                'page_index' => $index + 1,
                'image_path' => $path,
                'status' => JobPageStatus::Queued,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        JobPage::query()->insert($pages);

        StartJobProcessing::dispatch($job->id);

        $this->redirectRoute('jobs.progress', ['job' => $job->id]);
    }
}
