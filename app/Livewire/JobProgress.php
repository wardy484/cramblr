<?php

namespace App\Livewire;

use App\Enums\ExtractionJobStatus;
use App\Enums\JobPageStatus;
use App\Jobs\StartJobProcessing;
use App\Models\ExtractionJob;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class JobProgress extends Component
{
    public string $jobId;

    public function mount(ExtractionJob $job): void
    {
        abort_unless($job->user_id === Auth::id(), 403);

        $this->jobId = $job->id;
    }

    public function render(): View
    {
        $job = ExtractionJob::query()
            ->with('pages')
            ->findOrFail($this->jobId);

        return view('livewire.job-progress', [
            'job' => $job,
            'extractionPrompt' => $this->buildExtractionPrompt($job),
        ])->layout('layouts.app', ['title' => __('Job progress')]);
    }

    public function retry(): void
    {
        $job = ExtractionJob::query()
            ->with('pages')
            ->findOrFail($this->jobId);

        abort_unless($job->user_id === Auth::id(), 403);

        if ($job->status !== ExtractionJobStatus::Failed) {
            return;
        }

        $job->pages()->update([
            'status' => JobPageStatus::Queued,
            'extraction_json' => null,
            'raw_response' => null,
            'confidence' => null,
            'error_message' => null,
        ]);

        $job->update([
            'status' => ExtractionJobStatus::Queued,
            'progress_current' => 0,
            'progress_total' => $job->pages->count() + 1,
            'error_message' => null,
        ]);

        StartJobProcessing::dispatch($job->id);
    }

    /**
     * @return array{system: string, user: string, page_index: int}
     */
    private function buildExtractionPrompt(ExtractionJob $job): array
    {
        $pageIndex = (int) ($job->pages->first()?->page_index ?? 1);
        $translationPreference = $job->translation_preference ?? 'phonetic';

        $payload = app(OpenAIClient::class)->extractionPayload(
            'image',
            $job->refinement_prompt,
            $pageIndex,
            $translationPreference
        );

        $messages = $payload['messages'] ?? [];
        $system = $messages[0]['content'] ?? '';
        $user = '';

        if (isset($messages[1]['content']) && is_array($messages[1]['content'])) {
            foreach ($messages[1]['content'] as $content) {
                if (($content['type'] ?? null) === 'text') {
                    $user = (string) ($content['text'] ?? '');
                    break;
                }
            }
        }

        $system = $this->formatPromptWithJson($system);

        return [
            'system' => $system,
            'user' => $user,
            'page_index' => $pageIndex,
        ];
    }

    private function formatPromptWithJson(string $prompt): string
    {
        $pattern = '/Schema:\s*(\{[^}]+\})/';

        return preg_replace_callback($pattern, function ($matches) {
            $jsonString = $matches[1];

            $indent = 0;
            $formatted = '';
            $inString = false;
            $escapeNext = false;

            for ($i = 0; $i < strlen($jsonString); $i++) {
                $char = $jsonString[$i];

                if ($escapeNext) {
                    $formatted .= $char;
                    $escapeNext = false;

                    continue;
                }

                if ($char === '\\') {
                    $formatted .= $char;
                    $escapeNext = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = ! $inString;
                    $formatted .= $char;

                    continue;
                }

                if ($inString) {
                    $formatted .= $char;

                    continue;
                }

                if ($char === '{' || $char === '[') {
                    $formatted .= $char."\n".str_repeat('    ', ++$indent);
                } elseif ($char === '}' || $char === ']') {
                    $formatted .= "\n".str_repeat('    ', --$indent).$char;
                } elseif ($char === ',') {
                    $formatted .= $char."\n".str_repeat('    ', $indent);
                } elseif ($char === ':') {
                    $formatted .= ': ';
                } else {
                    $formatted .= $char;
                }
            }

            return 'Schema: '.$formatted.'.';
        }, $prompt);
    }
}
