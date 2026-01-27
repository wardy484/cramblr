<?php

namespace App\Jobs;

use App\Enums\JobPageStatus;
use App\Exceptions\InvalidOpenAIResponseException;
use App\Models\ExtractionJob;
use App\Models\JobPage;
use App\Services\Extraction\ImageResizer;
use App\Services\OpenAI\OpenAIClient;
use App\Services\OpenAI\ResponseValidator;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractPage implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 300;

    public function __construct(public string $pageId) {}

    /**
     * Execute the job.
     */
    public function handle(OpenAIClient $client, ResponseValidator $validator, ImageResizer $resizer): void
    {
        $page = JobPage::query()
            ->with('job')
            ->findOrFail($this->pageId);

        $job = $page->job;

        try {
            $binary = Storage::disk('private')->get($page->image_path);
            $resized = $resizer->resizeToMax($binary);
            $base64 = base64_encode($resized);

            [$parsed, $raw] = $this->extractWithRetry(
                $client,
                $validator,
                $base64,
                $job->refinement_prompt,
                $page->page_index,
                $job->translation_preference ?? 'phonetic'
            );

            $page->update([
                'extraction_json' => $parsed,
                'raw_response' => $raw,
                'confidence' => $this->averageConfidence($parsed),
                'status' => JobPageStatus::Extracted,
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $page->update([
                'status' => JobPageStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        } finally {
            ExtractionJob::query()->whereKey($job->id)->increment('progress_current');
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function extractWithRetry(
        OpenAIClient $client,
        ResponseValidator $validator,
        string $base64,
        ?string $refinementPrompt,
        int $pageIndex,
        string $translationPreference = 'phonetic'
    ): array {
        $payload = $client->extractionPayload($base64, $refinementPrompt, $pageIndex, $translationPreference);
        $response = $client->request($payload);
        $content = $client->responseContent($response);

        try {
            $parsed = $validator->validateExtraction($content, $pageIndex);
        } catch (InvalidOpenAIResponseException $exception) {
            $repairPayload = $this->repairPayload($client, $base64, $pageIndex, $content, $exception->getMessage(), $translationPreference);
            $response = $client->request($repairPayload);
            $content = $client->responseContent($response);
            $parsed = $validator->validateExtraction($content, $pageIndex);
        }

        return [$parsed, $response->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    private function repairPayload(
        OpenAIClient $client,
        string $base64,
        int $pageIndex,
        string $previousResponse,
        string $errorMessage,
        string $translationPreference = 'phonetic'
    ): array {
        $payload = $client->extractionPayload($base64, null, $pageIndex, $translationPreference);
        $translationInstruction = $translationPreference === 'thai'
            ? 'For the translation field, provide the Thai script.'
            : 'For the translation field, provide phonetic transcription (romanization).';
        $pronunciationInstruction = $translationPreference === 'thai'
            ? 'If romanization appears, put it in pronunciation.'
            : 'If Thai script appears, put it in pronunciation.';
        $payload['messages'] = [
            [
                'role' => 'system',
                'content' => implode(' ', [
                    'You repair JSON to match the required schema.',
                    'Return JSON only.',
                    'Use only the text on the page; do not translate or invent.',
                    'If both Thai script and romanization are present, map translation to the requested preference and pronunciation to the other.',
                    'If the requested translation is not present on the page, set translation to null.',
                    'Schema: {"language_guess":"string","page_type":"vocab_list|dialogue|grammar|mixed|unknown","items":[{"type":"vocab|phrase|sentence|grammar_point","source_text":"string","translation":null|string,"pronunciation":null|string,"notes":null|string,"page_index":number,"confidence":number}]}.',
                    "Important: Set page_index to {$pageIndex} for all items.",
                    $translationInstruction,
                    $pronunciationInstruction,
                ]),
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Fix the JSON. Error: {$errorMessage}. Previous response: {$previousResponse}",
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,'.$base64,
                        ],
                    ],
                ],
            ],
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function averageConfidence(array $parsed): ?float
    {
        $items = $parsed['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return null;
        }

        $total = 0.0;
        $count = 0;

        foreach ($items as $item) {
            if (is_array($item) && isset($item['confidence']) && is_numeric($item['confidence'])) {
                $total += (float) $item['confidence'];
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        return $total / $count;
    }
}
