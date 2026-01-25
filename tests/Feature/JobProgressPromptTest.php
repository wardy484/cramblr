<?php

use App\Enums\ExtractionJobStatus;
use App\Enums\JobPageStatus;
use App\Livewire\JobProgress;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('job progress shows extraction prompt text', function () {
    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Processing,
        'translation_preference' => 'thai',
        'refinement_prompt' => 'Focus on greetings.',
    ]);

    JobPage::factory()->for($job, 'job')->create([
        'page_index' => 1,
        'status' => JobPageStatus::Queued,
    ]);

    Livewire::actingAs($user)
        ->test(JobProgress::class, ['job' => $job])
        ->assertSee(__('Extraction prompt'))
        ->assertSee('break-words')
        ->assertSee('For the translation field, provide the Thai script.')
        ->assertSee('Focus on greetings.');
});

test('job progress shows phonetic prompt when preference is phonetic', function () {
    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Processing,
        'translation_preference' => 'phonetic',
        'refinement_prompt' => 'Focus on greetings.',
    ]);

    JobPage::factory()->for($job, 'job')->create([
        'page_index' => 1,
        'status' => JobPageStatus::Queued,
    ]);

    Livewire::actingAs($user)
        ->test(JobProgress::class, ['job' => $job])
        ->assertSee(__('Extraction prompt'))
        ->assertSee('For the translation field, provide phonetic transcription (romanization).')
        ->assertDontSee('For the translation field, provide the Thai script.');
});

test('job progress shows thai prompt when preference is thai', function () {
    $user = User::factory()->create();
    $job = ExtractionJob::factory()->for($user)->create([
        'status' => ExtractionJobStatus::Processing,
        'translation_preference' => 'thai',
        'refinement_prompt' => 'Focus on greetings.',
    ]);

    JobPage::factory()->for($job, 'job')->create([
        'page_index' => 1,
        'status' => JobPageStatus::Queued,
    ]);

    Livewire::actingAs($user)
        ->test(JobProgress::class, ['job' => $job])
        ->assertSee(__('Extraction prompt'))
        ->assertSee('For the translation field, provide the Thai script.')
        ->assertDontSee('For the translation field, provide phonetic transcription (romanization).');
});
