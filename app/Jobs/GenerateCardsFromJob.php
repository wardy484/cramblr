<?php

namespace App\Jobs;

use App\Enums\CardStatus;
use App\Enums\ExtractionJobStatus;
use App\Exceptions\InvalidOpenAIResponseException;
use App\Models\Card;
use App\Models\Deck;
use App\Models\ExtractionJob;
use App\Services\Deck\DeckSuggestionService;
use App\Services\OpenAI\OpenAIClient;
use App\Services\OpenAI\ResponseValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateCardsFromJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public string $jobId) {}

    /**
     * Execute the job.
     */
    public function handle(OpenAIClient $client, ResponseValidator $validator, DeckSuggestionService $suggestionService): void
    {
        $job = ExtractionJob::query()
            ->with('pages')
            ->findOrFail($this->jobId);

        try {
            $items = $this->collectItems($job);

            if ($items === []) {
                $job->update([
                    'status' => ExtractionJobStatus::Failed,
                    'error_message' => 'No extracted items available.',
                ]);

                return;
            }

            [$parsed, $raw] = $this->generateWithRetry($client, $validator, $items, $job->refinement_prompt, $job->translation_preference ?? 'phonetic');

            DB::transaction(function () use ($job, $parsed, $raw, $suggestionService): void {
                $job->update([
                    'generation_json' => $parsed,
                    'generation_raw' => $raw,
                    'status' => ExtractionJobStatus::NeedsReview,
                    'error_message' => null,
                ]);

                Card::query()->where('source_job_id', $job->id)->delete();

                $suggestedDecks = $suggestionService->suggestDecks($job->user_id, $parsed['cards']);

                foreach ($parsed['cards'] as $index => $cardData) {
                    $extra = $cardData['extra'] ?? [];
                    if (is_array($extra)) {
                        $extra['confidence'] = $cardData['confidence'] ?? null;
                        $extra['suggested_deck_id'] = $suggestedDecks[$index] ?? null;
                    }

                    $deckId = $suggestedDecks[$index] ?? $this->defaultDeckId($job->user_id);

                    $card = Card::query()->create([
                        'user_id' => $job->user_id,
                        'deck_id' => $deckId,
                        'status' => CardStatus::Proposed,
                        'front' => $cardData['front'],
                        'back' => $cardData['back'],
                        'tags' => $cardData['tags'],
                        'extra' => $extra,
                        'source_job_id' => $job->id,
                    ]);

                    if ($job->import_audio && ! empty($extra['thai_text'] ?? null)) {
                        GenerateCardAudio::dispatch($card->id);
                    }
                }
            });
        } catch (Throwable $exception) {
            $job->update([
                'status' => ExtractionJobStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(ExtractionJob $job): array
    {
        $items = [];

        foreach ($job->pages as $page) {
            $pageItems = $page->extraction_json['items'] ?? null;

            if (! is_array($pageItems)) {
                continue;
            }

            foreach ($pageItems as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function generateWithRetry(
        OpenAIClient $client,
        ResponseValidator $validator,
        array $items,
        ?string $refinementPrompt,
        string $translationPreference = 'phonetic'
    ): array {
        $payload = $client->cardGenerationPayload($items, $refinementPrompt, $translationPreference);
        $response = $client->request($payload);
        $content = $client->responseContent($response);

        try {
            $parsed = $validator->validateCards($content);
        } catch (InvalidOpenAIResponseException $exception) {
            $payload['messages'][0]['content'] = implode(' ', [
                'You repair JSON to match the required schema.',
                'Return JSON only.',
                'Front: Use phonetic transcription (romanization) from the pronunciation field, or translation field if pronunciation is not available.',
                'Back: Use English meaning from source_text field. If source_text is not English, use it as-is.',
                'Include extra.study_assist with explain, mnemonic, example.',
                'Schema: {"cards":[{"front":"string","back":"string","tags":["string"],"extra":{"source_text":"string","page_index":number,"thai_text":null|string,"study_assist":{"explain":string,"mnemonic":string,"example":string}},"confidence":number}]}.',
                'Error: '.$exception->getMessage(),
                'Previous response: '.$content,
            ]);

            $response = $client->request($payload);
            $content = $client->responseContent($response);
            $parsed = $validator->validateCards($content);
        }

        return [$parsed, $response->toArray()];
    }

    private function defaultDeckId(int $userId): string
    {
        return (string) Deck::query()
            ->firstOrCreate(
                ['user_id' => $userId, 'parent_id' => null, 'name' => 'Inbox'],
                ['description' => 'Default deck for new cards.']
            )
            ->id;
    }
}
