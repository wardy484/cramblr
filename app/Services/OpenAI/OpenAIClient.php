<?php

namespace App\Services\OpenAI;

use Illuminate\Container\Container;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\UnserializableResponse;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Throwable;

class OpenAIClient
{
    public function request(array $payload): CreateResponse
    {
        $metadata = $payload['metadata'] ?? [];
        $apiPayload = $payload;
        unset($apiPayload['metadata']);

        $payloadSize = $this->payloadSize($apiPayload);
        $model = $apiPayload['model'] ?? 'unknown';

        return retry(
            3,
            function (int $attempt) use ($apiPayload, $payloadSize, $model, $metadata): CreateResponse {
                $this->logInfo('OpenAI request attempt', [
                    'attempt' => $attempt,
                    'model' => $model,
                    'payload_size' => $payloadSize,
                    'metadata' => $metadata,
                    'payload_structure' => $this->getPayloadStructure($apiPayload),
                ]);

                try {
                    $response = OpenAI::chat()->create($apiPayload);
                    $meta = $response->meta();

                    $this->logInfo('OpenAI response received', [
                        'request_id' => $meta->requestId,
                        'processing_ms' => $meta->openai->processingMs,
                        'model' => $meta->openai->model,
                    ]);

                    return $response;
                } catch (Throwable $exception) {
                    $errorContext = [
                        'attempt' => $attempt,
                        'model' => $model,
                        'payload_size' => $payloadSize,
                        'metadata' => $metadata,
                        'error' => $exception->getMessage(),
                        'exception_class' => get_class($exception),
                    ];

                    if ($exception instanceof ErrorException) {
                        $errorContext['error_type'] = $exception->getErrorType();
                        $errorContext['error_code'] = $exception->getErrorCode();
                        $errorContext['status_code'] = $exception->getStatusCode();
                    }

                    if ($exception instanceof UnserializableResponse) {
                        $response = $exception->response;
                        $errorContext['response_status'] = $response->getStatusCode();
                        
                        $body = $response->getBody();
                        if ($body->isSeekable()) {
                            $body->rewind();
                        }
                        $bodyContent = (string) $body;
                        
                        $errorContext['response_body_preview'] = substr($bodyContent, 0, 500);
                        $errorContext['response_body_length'] = strlen($bodyContent);
                        $errorContext['response_headers'] = $response->getHeaders();
                    }

                    $this->logWarning('OpenAI request failed', $errorContext);

                    throw $exception;
                }
            },
            500,
            function (Throwable $exception): bool {
                return $this->shouldRetry($exception);
            }
        );
    }

    public function responseContent(CreateResponse $response): string
    {
        return (string) ($response->choices[0]->message->content ?? '');
    }

    public function extractionPayload(string $base64Image, ?string $refinementPrompt, int $pageIndex, string $translationPreference = 'phonetic'): array
    {
        $prompt = trim('Extract Thai study content from this page: vocab, phrases, sentences, and grammar points. '.($refinementPrompt ?? ''));

        $translationInstruction = $translationPreference === 'thai'
            ? 'For the translation field, provide the Thai script.'
            : 'For the translation field, provide phonetic transcription (romanization).';

        $pronunciationInstruction = $translationPreference === 'thai'
            ? 'If romanization appears, put it in pronunciation.'
            : 'If Thai script appears, put it in pronunciation.';

        return [
            'model' => config('openai.vision_model'),
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => implode(' ', [
                        'You are a precise extraction engine focused on Thai study materials.',
                        'Return JSON only, no markdown.',
                        'Never hallucinate. If a field is unknown, set it to null and lower confidence.',
                        'Extract only learnable items: vocab, phrases, sentences, and grammar points.',
                        'Ignore page numbers, headers, table labels, and instructions unless they contain learnable text.',
                        'Use only the text on the page; do not translate or invent.',
                        'If English meaning is present, put it in source_text; otherwise use the original text.',
                        'If both Thai script and romanization are present, map translation to the requested preference and pronunciation to the other.',
                        'If the requested translation is not present on the page, set translation to null.',
                        $translationInstruction,
                        $pronunciationInstruction,
                        'Use notes for brief grammar or usage hints only.',
                        'Schema: {"language_guess":"string","page_type":"vocab_list|dialogue|grammar|mixed|unknown","items":[{"type":"vocab|phrase|sentence|grammar_point","source_text":"string","translation":null|string,"pronunciation":null|string,"notes":null|string,"page_index":number,"confidence":number}]}.',
                        "Important: Set page_index to {$pageIndex} for all items.",
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:image/jpeg;base64,'.$base64Image,
                            ],
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'page_index' => $pageIndex,
            ],
        ];
    }

    public function cardGenerationPayload(array $items, ?string $refinementPrompt, string $translationPreference = 'phonetic'): array
    {
        $content = [
            'Create flashcards from the extracted items for studying Thai.',
            'Return JSON only.',
            'Front: Use phonetic transcription (romanization) from the pronunciation field, or translation field if pronunciation is not available.',
            'Back: Use English meaning from source_text field. If source_text is not English, use it as-is.',
            'In extra, include thai_text field with the Thai script from translation field (if translation_preference was thai) or pronunciation field (if translation_preference was phonetic).',
            'Also include extra.study_assist with keys: explain, mnemonic, example. Keep each concise and helpful.',
            'Schema: {"cards":[{"front":"string","back":"string","tags":["string"],"extra":{"source_text":"string","page_index":number,"thai_text":null|string,"study_assist":{"explain":string,"mnemonic":string,"example":string}},"confidence":number}]}.',
            'Never hallucinate. If uncertain, set fields to null and lower confidence.',
        ];

        if ($refinementPrompt !== null && trim($refinementPrompt) !== '') {
            $content[] = 'Refinement prompt: '.$refinementPrompt;
        }

        $content[] = 'Items JSON: '.json_encode($items, JSON_THROW_ON_ERROR);

        return [
            'model' => config('openai.cards_model'),
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => implode(' ', $content),
                ],
            ],
        ];
    }

    public function simpleTextPayload(string $prompt): array
    {
        return [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];
    }

    public function studyAssistPayload(string $front, string $back, string $mode): array
    {
        $goal = match ($mode) {
            'mnemonic' => 'Give a short mnemonic or memory hook.',
            'example' => 'Provide a concise example usage.',
            default => 'Explain the concept in simple terms.',
        };

        $prompt = implode("\n", [
            'You are a study coach.',
            $goal,
            'Keep it concise and practical.',
            'Front: '.$front,
            'Back: '.$back,
        ]);

        return $this->simpleTextPayload($prompt);
    }

    public function generateSpeech(string $text, string $voice = 'alloy'): string
    {
        return OpenAI::audio()->speech([
            'model' => config('openai.tts_model', 'tts-1'),
            'input' => $text,
            'voice' => $voice,
            'response_format' => 'mp3',
        ]);
    }

    private function shouldRetry(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'cURL error 18')
            || str_contains($message, 'Connection')
            || str_contains($message, 'timed out');
    }

    private function payloadSize(array $payload): ?int
    {
        try {
            return strlen(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context): void
    {
        $container = Container::getInstance();

        if (! $container->bound('log')) {
            return;
        }

        $container->make('log')->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logWarning(string $message, array $context): void
    {
        $container = Container::getInstance();

        if (! $container->bound('log')) {
            return;
        }

        $container->make('log')->warning($message, $context);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function getPayloadStructure(array $payload): array
    {
        $structure = [];

        foreach ($payload as $key => $value) {
            if ($key === 'messages' && is_array($value)) {
                $structure[$key] = array_map(function ($message) {
                    if (isset($message['content']) && is_array($message['content'])) {
                        return [
                            'role' => $message['role'] ?? null,
                            'content' => array_map(function ($item) {
                                if (isset($item['type'])) {
                                    if ($item['type'] === 'image_url') {
                                        return [
                                            'type' => 'image_url',
                                            'image_url' => [
                                                'url' => isset($item['image_url']['url']) 
                                                    ? substr($item['image_url']['url'], 0, 50).'...' 
                                                    : null,
                                            ],
                                        ];
                                    }
                                    return ['type' => $item['type'], 'text' => substr((string) ($item['text'] ?? ''), 0, 100)];
                                }
                                return $item;
                            }, $message['content']),
                        ];
                    }
                    return ['role' => $message['role'] ?? null, 'content' => substr((string) ($message['content'] ?? ''), 0, 100)];
                }, $value);
            } else {
                $structure[$key] = $value;
            }
        }

        return $structure;
    }
}
