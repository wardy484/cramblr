<?php

namespace App\Services\Deck;

use App\Models\Card;
use App\Models\Deck;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Support\Collection;

class DeckSuggestionService
{
    public function __construct(
        private readonly OpenAIClient $client
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<int, string>
     */
    public function suggestDecks(int $userId, array $cards): array
    {
        $decks = Deck::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();

        if ($decks->isEmpty()) {
            $defaultDeck = Deck::query()->firstOrCreate(
                ['user_id' => $userId, 'parent_id' => null, 'name' => 'Inbox'],
                ['description' => 'Default deck for new cards.']
            );

            return array_fill(0, count($cards), (string) $defaultDeck->id);
        }

        $eligibleDecks = $this->eligibleDecks($decks);
        $hasGenericDecks = $decks->contains(function (Deck $deck): bool {
            return $this->isGenericDeckName($deck->name);
        });
        $deckCounts = $this->getDeckCounts($userId, $eligibleDecks);
        $suggestions = $this->getAISuggestions($userId, $cards, $eligibleDecks, $deckCounts, $hasGenericDecks);
        $suggestions = $this->normalizeSuggestionsByPage($userId, $cards, $suggestions, $eligibleDecks, $deckCounts);

        return $this->balanceDistribution($suggestions, $eligibleDecks, $deckCounts, count($cards));
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<string, array<int, int>>
     */
    private function groupCardIndexesByPage(array $cards): array
    {
        $groups = [];

        foreach ($cards as $index => $card) {
            $pageIndex = $card['extra']['page_index'] ?? $card['page_index'] ?? null;
            $key = is_numeric($pageIndex) ? (string) (int) $pageIndex : 'unknown';
            $groups[$key][] = $index;
        }

        return $groups;
    }

    /**
     * @param Collection<int, Deck> $decks
     * @return array<string, int>
     */
    private function getDeckCounts(int $userId, Collection $decks): array
    {
        $counts = Card::query()
            ->where('user_id', $userId)
            ->selectRaw('deck_id, COUNT(*) as total')
            ->groupBy('deck_id')
            ->pluck('total', 'deck_id')
            ->all();

        $result = [];
        foreach ($decks as $deck) {
            $result[$deck->id] = (int) ($counts[$deck->id] ?? 0);
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @param Collection<int, Deck> $decks
     * @param array<string, int> $deckCounts
     * @return array<int, string>
     */
    private function getAISuggestions(
        int $userId,
        array $cards,
        Collection $decks,
        array $deckCounts,
        bool $hasGenericDecks
    ): array
    {
        if (empty($cards)) {
            return [];
        }

        $deckSamples = $this->getDeckSamples($userId, $decks);

        $deckInfo = $decks->map(function (Deck $deck) use ($deckCounts, $deckSamples): array {
            return [
                'id' => $deck->id,
                'name' => $deck->name,
                'description' => $deck->description ?? '',
                'current_count' => $deckCounts[$deck->id] ?? 0,
                'is_generic' => $this->isGenericDeckName($deck->name),
                'sample_cards' => $deckSamples[$deck->id] ?? [],
            ];
        })->values()->all();

        $cardSummaries = array_map(function (array $card, int $index): array {
            return [
                'index' => $index,
                'front' => $card['front'] ?? '',
                'back' => $card['back'] ?? '',
                'tags' => $card['tags'] ?? [],
            ];
        }, $cards, array_keys($cards));

        $prompt = $this->buildSuggestionPrompt($cardSummaries, $deckInfo, $hasGenericDecks);
        $payload = $this->client->simpleTextPayload($prompt);
        $payload['model'] = config('openai.cards_model');
        $payload['response_format'] = ['type' => 'json_object'];
        $payload['temperature'] = 0.3;

        $response = $this->client->request($payload);
        $content = $this->client->responseContent($response);

        return $this->parseSuggestions($userId, $content, $cards, $decks, $deckCounts);
    }

    /**
     * @param array<int, array<string, mixed>> $cardSummaries
     * @param array<int, array<string, mixed>> $deckInfo
     */
    private function buildSuggestionPrompt(array $cardSummaries, array $deckInfo, bool $hasGenericDecks): string
    {
        $deckJson = json_encode($deckInfo, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $cardsJson = json_encode($cardSummaries, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $genericHint = $hasGenericDecks
            ? 'Generic decks exist. Do not suggest generic decks for topical cards.'
            : 'No generic decks exist.';

        return <<<PROMPT
You are a deck organization assistant for a Thai language learning flashcard app.

Analyze the following cards and suggest which deck each card should go into based on:
1. Card content (front/back text, tags)
2. Deck names, descriptions, and sample cards already in each deck
3. Current deck sizes (to help balance distribution)

Available decks:
{$deckJson}

Cards to organize (cards from the same page should share the same category):
{$cardsJson}

{$genericHint}

Return JSON in this format:
{
  "suggestions": [
    {"card_index": 0, "deck_id": "uuid", "reason": "brief explanation"},
    {"card_index": 1, "new_deck_name": "Topic Name", "reason": "brief explanation"},
    ...
  ]
}

Guidelines:
- If multiple cards are from the same page, keep them in one cohesive deck
- Match cards to decks based on content similarity and topic
- Consider deck names and descriptions as hints about their purpose
- Use sample_cards to understand each deck's theme
- Prefer decks that are smaller to help balance distribution
- Avoid generic decks like "Inbox" or "New Deck" for topical content
- Only suggest a new deck when none of the existing decks are a good fit
- If only generic decks exist, prefer suggesting a new deck for topical cards
- Each card_index must correspond to a card in the input
- Each deck_id must be a valid UUID from the available decks
- new_deck_name must be a short, descriptive title (2-4 words)
- Never use generic names like "new deck", "misc", "general", "other"
PROMPT;
    }

    /**
     * @param Collection<int, Deck> $decks
     * @param array<string, int> $deckCounts
     * @return array<int, string>
     */
    private function parseSuggestions(
        int $userId,
        string $json,
        array $cards,
        Collection $decks,
        array $deckCounts
    ): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->fallbackSuggestions($cards, $decks, $deckCounts, $userId);
        }

        if (!is_array($decoded['suggestions'] ?? null)) {
            return $this->fallbackSuggestions($cards, $decks, $deckCounts, $userId);
        }

        $deckIds = $decks->pluck('id')->all();
        $hasNonGenericDecks = $decks->contains(function (Deck $deck): bool {
            return ! $this->isGenericDeckName($deck->name);
        });
        $suggestions = [];

        foreach ($decoded['suggestions'] as $suggestion) {
            $index = $suggestion['card_index'] ?? null;
            $deckId = $suggestion['deck_id'] ?? null;
            $newDeckName = $suggestion['new_deck_name'] ?? null;

            if (!is_numeric($index)) {
                continue;
            }

            if (is_string($deckId) && in_array($deckId, $deckIds, true)) {
                $deckName = $decks->firstWhere('id', $deckId)?->name;
                if ($deckName !== null && $this->isGenericDeckName($deckName)) {
                    continue;
                }
                $suggestions[(int) $index] = $deckId;
                continue;
            }

            $normalizedDeckName = $this->normalizeNewDeckName($newDeckName);

            $createdDeckId = $this->createSuggestedDeck($userId, $normalizedDeckName, $deckIds, $decks, $deckCounts);
            if ($createdDeckId !== null) {
                $suggestions[(int) $index] = $createdDeckId;
                continue;
            }

            if (! $hasNonGenericDecks && isset($cards[(int) $index])) {
                $fallbackName = $this->deriveDeckNameFromCard($cards[(int) $index]);
                $createdDeckId = $this->createSuggestedDeck($userId, $fallbackName, $deckIds, $decks, $deckCounts);
                if ($createdDeckId !== null) {
                    $suggestions[(int) $index] = $createdDeckId;
                }
            }
        }

        $cardCount = count($cards);
        for ($i = 0; $i < $cardCount; $i++) {
            if (!isset($suggestions[$i])) {
                if (! $hasNonGenericDecks) {
                    $fallbackName = $this->deriveDeckNameFromCard($cards[$i] ?? []);
                    $createdDeckId = $this->createSuggestedDeck($userId, $fallbackName, $deckIds, $decks, $deckCounts);
                    if ($createdDeckId !== null) {
                        $suggestions[$i] = $createdDeckId;
                        continue;
                    }
                }

                $suggestions[$i] = $this->findSmallestDeck($decks, $deckCounts, []);
            }
        }

        return $suggestions;
    }

    /**
     * @param Collection<int, Deck> $decks
     * @param array<string, int> $deckCounts
     * @return array<int, string>
     */
    private function fallbackSuggestions(
        array $cards,
        Collection $decks,
        array $deckCounts,
        int $userId
    ): array
    {
        $hasNonGenericDecks = $decks->contains(function (Deck $deck): bool {
            return ! $this->isGenericDeckName($deck->name);
        });
        $deckIds = $decks->pluck('id')->all();
        $suggestions = [];
        $cardCount = count($cards);

        for ($i = 0; $i < $cardCount; $i++) {
            if (! $hasNonGenericDecks) {
                $fallbackName = $this->deriveDeckNameFromCard($cards[$i] ?? []);
                $createdDeckId = $this->createSuggestedDeck($userId, $fallbackName, $deckIds, $decks, $deckCounts);
                if ($createdDeckId !== null) {
                    $suggestions[$i] = $createdDeckId;
                    continue;
                }
            }

            $suggestions[$i] = $this->findSmallestDeck($decks, $deckCounts, []);
        }

        return $suggestions;
    }

    /**
     * @param array<int, string> $suggestions
     * @param Collection<int, Deck> $decks
     * @param array<string, int> $deckCounts
     * @return array<int, string>
     */
    private function balanceDistribution(array $suggestions, Collection $decks, array $deckCounts, int $totalCards): array
    {
        $deckIds = $decks->pluck('id')->all();
        $existingTotal = array_sum($deckCounts);
        $targetTotal = (int) ceil(($existingTotal + $totalCards) / count($decks));
        $maxTotal = (int) ceil($targetTotal * 1.25);

        $distribution = array_fill_keys($deckIds, 0);
        $balanced = [];

        foreach ($suggestions as $index => $deckId) {
            $currentCount = $deckCounts[$deckId] ?? 0;
            $newCount = $distribution[$deckId] + 1;

            if ($currentCount + $newCount > $maxTotal) {
                $smallestDeckId = $this->findSmallestDeck($decks, $deckCounts, $distribution);
                $balanced[$index] = $smallestDeckId;
                $distribution[$smallestDeckId]++;
            } else {
                $balanced[$index] = $deckId;
                $distribution[$deckId]++;
            }
        }

        return $balanced;
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @param array<int, string> $suggestions
     * @param Collection<int, Deck> $decks
     * @param array<string, int> $deckCounts
     * @return array<int, string>
     */
    private function normalizeSuggestionsByPage(
        int $userId,
        array $cards,
        array $suggestions,
        Collection $decks,
        array &$deckCounts
    ): array {
        $groups = $this->groupCardIndexesByPage($cards);
        $deckIds = $decks->pluck('id')->all();
        $hasNonGenericDecks = $decks->contains(function (Deck $deck): bool {
            return ! $this->isGenericDeckName($deck->name);
        });

        foreach ($groups as $indexes) {
            $counts = [];
            foreach ($indexes as $index) {
                $deckId = $suggestions[$index] ?? null;
                if (is_string($deckId)) {
                    $counts[$deckId] = ($counts[$deckId] ?? 0) + 1;
                }
            }

            arsort($counts);
            $chosenDeckId = $counts !== [] ? array_key_first($counts) : null;

            if (is_string($chosenDeckId) && in_array($chosenDeckId, $deckIds, true)) {
                $deckName = $decks->firstWhere('id', $chosenDeckId)?->name;
                if ($deckName !== null && $this->isGenericDeckName($deckName)) {
                    $chosenDeckId = null;
                }
            } else {
                $chosenDeckId = null;
            }

            if ($chosenDeckId === null && $indexes !== []) {
                $fallbackName = $this->deriveDeckNameFromCard($cards[$indexes[0]] ?? []);
                $createdDeckId = $this->createSuggestedDeck($userId, $fallbackName, $deckIds, $decks, $deckCounts);
                if ($createdDeckId !== null) {
                    $chosenDeckId = $createdDeckId;
                }
            }

            if ($chosenDeckId === null && $hasNonGenericDecks) {
                $chosenDeckId = $this->findSmallestDeck($decks, $deckCounts, []);
            }

            if ($chosenDeckId !== null) {
                foreach ($indexes as $index) {
                    $suggestions[$index] = $chosenDeckId;
                }
            }
        }

        return $suggestions;
    }

    /**
     * @param Collection<int, Deck> $decks
     * @param array<string, int> $deckCounts
     * @param array<string, int> $distribution
     */
    private function findSmallestDeck(Collection $decks, array $deckCounts, array $distribution): string
    {
        $nonGenericDecks = $decks->filter(function (Deck $deck): bool {
            return ! $this->isGenericDeckName($deck->name);
        });

        $candidates = $nonGenericDecks->isNotEmpty() ? $nonGenericDecks : $decks;
        $smallestId = null;
        $smallestTotal = PHP_INT_MAX;

        foreach ($candidates as $deck) {
            $current = $deckCounts[$deck->id] ?? 0;
            $new = $distribution[$deck->id] ?? 0;
            $total = $current + $new;

            if ($total < $smallestTotal) {
                $smallestTotal = $total;
                $smallestId = $deck->id;
            }
        }

        return (string) ($smallestId ?? $candidates->first()->id);
    }

    /**
     * @param Collection<int, Deck> $decks
     */
    private function eligibleDecks(Collection $decks): Collection
    {
        $nonGeneric = $decks->filter(function (Deck $deck): bool {
            return ! $this->isGenericDeckName($deck->name);
        });

        return $nonGeneric->isNotEmpty() ? $nonGeneric->values() : $decks;
    }

    private function createSuggestedDeck(
        int $userId,
        ?string $name,
        array &$deckIds,
        Collection $decks,
        array &$deckCounts
    ): ?string {
        if ($name === null) {
            return null;
        }

        $createdDeck = Deck::query()->firstOrCreate(
            ['user_id' => $userId, 'parent_id' => null, 'name' => $name],
            ['description' => 'AI-suggested deck.']
        );

        if (!in_array($createdDeck->id, $deckIds, true)) {
            $deckIds[] = $createdDeck->id;
            $decks->push($createdDeck);
            $deckCounts[$createdDeck->id] = 0;
        }

        return (string) $createdDeck->id;
    }

    private function deriveDeckNameFromCard(array $card): ?string
    {
        $tags = $card['tags'] ?? [];
        if (is_array($tags) && $tags !== []) {
            $tag = (string) $tags[0];
            $clean = trim(preg_replace('/[_-]+/', ' ', $tag));
            $clean = preg_replace('/\s+/', ' ', $clean);
            if ($clean !== '') {
                $name = ucwords(strtolower($clean));
                return $this->normalizeNewDeckName($name);
            }
        }

        $back = trim((string) ($card['back'] ?? ''));
        if ($back !== '') {
            $words = preg_split('/\s+/', strtolower($back));
            $words = array_values(array_filter($words, fn (string $word): bool => strlen($word) > 2));
            $name = implode(' ', array_slice($words, 0, 3));
            if ($name !== '') {
                return $this->normalizeNewDeckName(ucwords($name));
            }
        }

        return null;
    }

    /**
     * @param Collection<int, Deck> $decks
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getDeckSamples(int $userId, Collection $decks): array
    {
        $deckIds = $decks->pluck('id')->all();
        if ($deckIds === []) {
            return [];
        }

        $cards = Card::query()
            ->where('user_id', $userId)
            ->whereIn('deck_id', $deckIds)
            ->orderByDesc('created_at')
            ->get(['deck_id', 'front', 'back', 'tags']);

        $samples = [];
        foreach ($cards as $card) {
            $deckId = (string) $card->deck_id;
            if (!isset($samples[$deckId])) {
                $samples[$deckId] = [];
            }
            if (count($samples[$deckId]) >= 3) {
                continue;
            }

            $samples[$deckId][] = [
                'front' => $card->front,
                'back' => $card->back,
                'tags' => $card->tags ?? [],
            ];
        }

        return $samples;
    }

    private function isGenericDeckName(string $name): bool
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $generic = ['inbox', 'new deck', 'deck', 'misc', 'general', 'other', 'untitled'];

        return in_array($normalized, $generic, true);
    }

    private function normalizeNewDeckName(mixed $name): ?string
    {
        if (!is_string($name)) {
            return null;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $trimmed));
        $blocked = ['inbox', 'new deck', 'deck', 'misc', 'general', 'other', 'untitled'];

        if (in_array($normalized, $blocked, true)) {
            return null;
        }

        return $trimmed;
    }
}
