<?php

namespace App\Services\OpenAI;

use App\Exceptions\InvalidOpenAIResponseException;

class ResponseValidator
{
    /**
     * @return array<string, mixed>
     */
    public function validateExtraction(string $json, int $pageIndex): array
    {
        $decoded = $this->decodeJson($json);

        $pageType = $decoded['page_type'] ?? null;
        $allowedPageTypes = ['vocab_list', 'dialogue', 'grammar', 'mixed', 'unknown'];

        if (!is_string($decoded['language_guess'] ?? null)) {
            throw new InvalidOpenAIResponseException('Missing or invalid language_guess.');
        }

        if (!is_string($pageType) || !in_array($pageType, $allowedPageTypes, true)) {
            throw new InvalidOpenAIResponseException('Missing or invalid page_type.');
        }

        if (!is_array($decoded['items'] ?? null)) {
            throw new InvalidOpenAIResponseException('Missing or invalid items.');
        }

        foreach ($decoded['items'] as $index => $item) {
            $this->validateExtractionItem($item, $index, $pageIndex);
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateCards(string $json): array
    {
        $decoded = $this->decodeJson($json);

        if (!is_array($decoded['cards'] ?? null)) {
            throw new InvalidOpenAIResponseException('Missing or invalid cards array.');
        }

        foreach ($decoded['cards'] as $index => $card) {
            $this->validateCardItem($card, $index);
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new InvalidOpenAIResponseException('Invalid JSON response.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidOpenAIResponseException('JSON response must decode to an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function validateExtractionItem(array $item, int $index, int $pageIndex): void
    {
        $allowedTypes = ['vocab', 'phrase', 'sentence', 'grammar_point'];

        if (!is_string($item['type'] ?? null) || !in_array($item['type'], $allowedTypes, true)) {
            throw new InvalidOpenAIResponseException("Invalid item type at index {$index}.");
        }

        if (!is_string($item['source_text'] ?? null)) {
            throw new InvalidOpenAIResponseException("Missing source_text at index {$index}.");
        }

        foreach (['translation', 'pronunciation', 'notes'] as $field) {
            $value = $item[$field] ?? null;
            if (!is_null($value) && !is_string($value)) {
                throw new InvalidOpenAIResponseException("Invalid {$field} at index {$index}.");
            }
        }

        if (!isset($item['page_index']) || !is_numeric($item['page_index'])) {
            throw new InvalidOpenAIResponseException("Invalid page_index at index {$index}.");
        }

        if ((int) $item['page_index'] !== $pageIndex) {
            throw new InvalidOpenAIResponseException("page_index mismatch at index {$index}.");
        }

        if (!isset($item['confidence']) || !is_numeric($item['confidence'])) {
            throw new InvalidOpenAIResponseException("Invalid confidence at index {$index}.");
        }
    }

    /**
     * @param array<string, mixed> $card
     */
    private function validateCardItem(array $card, int $index): void
    {
        if (!is_string($card['front'] ?? null)) {
            throw new InvalidOpenAIResponseException("Missing front at index {$index}.");
        }

        if (!is_string($card['back'] ?? null)) {
            throw new InvalidOpenAIResponseException("Missing back at index {$index}.");
        }

        if (!is_array($card['tags'] ?? null)) {
            throw new InvalidOpenAIResponseException("Invalid tags at index {$index}.");
        }

        foreach ($card['tags'] as $tag) {
            if (!is_string($tag)) {
                throw new InvalidOpenAIResponseException("Invalid tag type at index {$index}.");
            }
        }

        $extra = $card['extra'] ?? null;
        if (!is_array($extra)) {
            throw new InvalidOpenAIResponseException("Invalid extra at index {$index}.");
        }

        if (!is_string($extra['source_text'] ?? null)) {
            throw new InvalidOpenAIResponseException("Missing extra.source_text at index {$index}.");
        }

        if (!isset($extra['page_index']) || !is_numeric($extra['page_index'])) {
            throw new InvalidOpenAIResponseException("Missing extra.page_index at index {$index}.");
        }

        if (isset($extra['study_assist'])) {
            if (!is_array($extra['study_assist'])) {
                throw new InvalidOpenAIResponseException("Invalid extra.study_assist at index {$index}.");
            }

            foreach (['explain', 'mnemonic', 'example'] as $field) {
                $value = $extra['study_assist'][$field] ?? null;
                if (!is_null($value) && !is_string($value)) {
                    throw new InvalidOpenAIResponseException("Invalid extra.study_assist.{$field} at index {$index}.");
                }
            }
        }

        if (!isset($card['confidence']) || !is_numeric($card['confidence'])) {
            throw new InvalidOpenAIResponseException("Invalid confidence at index {$index}.");
        }
    }
}
