<?php

namespace App\Livewire;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardReview;
use App\Models\Deck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard')
            ->layout('layouts.app', ['title' => __('Dashboard')]);
    }

    /**
     * @return array<int, array{deck_id: string, deck_name: string, review_due: int, new_count: int}>
     */
    #[Computed]
    public function reviewDecks(): array
    {
        $summaries = $this->deckSummaries();
        $reviewDecks = array_values(array_filter($summaries, function (array $deck): bool {
            return $deck['review_due'] > 0;
        }));

        usort($reviewDecks, function (array $left, array $right): int {
            return $right['review_due'] <=> $left['review_due'];
        });

        return $reviewDecks;
    }

    /**
     * @return array<int, array{deck_id: string, deck_name: string, review_due: int, new_count: int}>
     */
    #[Computed]
    public function learnDecks(): array
    {
        $summaries = $this->deckSummaries();
        $learnDecks = array_values(array_filter($summaries, function (array $deck): bool {
            return $deck['new_count'] > 0;
        }));

        usort($learnDecks, function (array $left, array $right): int {
            return $right['new_count'] <=> $left['new_count'];
        });

        return $learnDecks;
    }

    /**
     * @return array{deck_id: string, deck_name: string, reviewed_at: \Carbon\CarbonInterface}|null
     */
    #[Computed]
    public function recentDeck(): ?array
    {
        $review = CardReview::query()
            ->where('card_reviews.user_id', Auth::id())
            ->join('cards', 'cards.id', '=', 'card_reviews.card_id')
            ->join('decks', 'decks.id', '=', 'cards.deck_id')
            ->where('cards.user_id', Auth::id())
            ->select([
                'decks.id as deck_id',
                'decks.name as deck_name',
                'card_reviews.reviewed_at',
            ])
            ->orderByDesc('card_reviews.reviewed_at')
            ->first();

        if (! $review) {
            return null;
        }

        $reviewedAt = $review->reviewed_at instanceof \Carbon\CarbonInterface
            ? $review->reviewed_at
            : CarbonImmutable::parse($review->reviewed_at);

        return [
            'deck_id' => (string) $review->deck_id,
            'deck_name' => (string) $review->deck_name,
            'reviewed_at' => $reviewedAt,
        ];
    }

    /**
     * @return array<int, array{deck_id: string, deck_name: string, reviewed_at: \Carbon\CarbonInterface, review_count: int}>
     */
    #[Computed]
    public function recentHistory(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        $rows = CardReview::query()
            ->where('card_reviews.user_id', $userId)
            ->join('cards', 'cards.id', '=', 'card_reviews.card_id')
            ->join('decks', 'decks.id', '=', 'cards.deck_id')
            ->where('cards.user_id', $userId)
            ->selectRaw('decks.id as deck_id, decks.name as deck_name, MAX(card_reviews.reviewed_at) as reviewed_at, COUNT(*) as review_count')
            ->groupBy('decks.id', 'decks.name')
            ->orderByDesc('reviewed_at')
            ->limit(5)
            ->get();

        return $rows->map(function ($row): array {
            $reviewedAt = $row->reviewed_at instanceof \Carbon\CarbonInterface
                ? $row->reviewed_at
                : CarbonImmutable::parse($row->reviewed_at);

            return [
                'deck_id' => (string) $row->deck_id,
                'deck_name' => (string) $row->deck_name,
                'reviewed_at' => $reviewedAt,
                'review_count' => (int) $row->review_count,
            ];
        })->all();
    }

    /**
     * @return array{type: string, deck_id: string, deck_name: string, count: int}|null
     */
    #[Computed]
    public function recommended(): ?array
    {
        $recentDeckId = $this->recentDeck['deck_id'] ?? null;
        $recentReviewedAt = $this->recentDeck['reviewed_at'] ?? null;
        $shouldAvoidRecent = $recentReviewedAt && $recentReviewedAt->greaterThan(now()->subHours(6));

        if ($this->reviewDecks !== []) {
            $candidate = null;
            foreach ($this->reviewDecks as $deck) {
                if ($shouldAvoidRecent && $recentDeckId && $deck['deck_id'] === $recentDeckId) {
                    continue;
                }
                $candidate = $deck;
                break;
            }

            if (! $candidate) {
                $candidate = $this->reviewDecks[0];
            }

            return [
                'type' => 'review',
                'deck_id' => $candidate['deck_id'],
                'deck_name' => $candidate['deck_name'],
                'count' => $candidate['review_due'],
            ];
        }

        if ($this->learnDecks !== []) {
            $deck = $this->learnDecks[0];

            return [
                'type' => 'learn',
                'deck_id' => $deck['deck_id'],
                'deck_name' => $deck['deck_name'],
                'count' => $deck['new_count'],
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{deck_id: string, deck_name: string, review_due: int, new_count: int}>
     */
    private function deckSummaries(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        $decks = Deck::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($decks->isEmpty()) {
            return [];
        }

        $reviewCounts = Card::query()
            ->where('user_id', $userId)
            ->where('status', CardStatus::Approved)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->selectRaw('deck_id, COUNT(*) as total')
            ->groupBy('deck_id')
            ->pluck('total', 'deck_id');

        $newCounts = Card::query()
            ->where('user_id', $userId)
            ->where('status', CardStatus::Approved)
            ->where(function ($builder): void {
                $builder->whereNull('study_state')
                    ->orWhere('study_state', 'new');
            })
            ->selectRaw('deck_id, COUNT(*) as total')
            ->groupBy('deck_id')
            ->pluck('total', 'deck_id');

        $summaries = [];

        foreach ($decks as $deck) {
            $deckId = (string) $deck->id;
            $summaries[] = [
                'deck_id' => $deckId,
                'deck_name' => $deck->name,
                'review_due' => (int) ($reviewCounts[$deckId] ?? 0),
                'new_count' => (int) ($newCounts[$deckId] ?? 0),
            ];
        }

        return $summaries;
    }
}
