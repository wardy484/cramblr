<?php

namespace App\Livewire;

use App\Enums\CardStatus;
use App\Enums\ExtractionJobStatus;
use App\Models\Card;
use App\Models\Deck;
use App\Models\ExtractionJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

class CardReview extends Component
{
    public string $jobId;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $cards = [];

    /**
     * @var array<string, bool>
     */
    public array $selected = [];

    /**
     * @var array<int, string>
     */
    public array $pageMap = [];

    public function mount(ExtractionJob $job): void
    {
        abort_unless($job->user_id === Auth::id(), 403);

        $this->jobId = $job->id;
        $this->pageMap = $job->pages()->pluck('id', 'page_index')->map(fn ($id) => (string) $id)->all();
        $this->loadCards();
    }

    public function render(): View
    {
        return view('livewire.card-review', [
            'decks' => Deck::query()
                ->where('user_id', Auth::id())
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['title' => __('Review cards')]);
    }

    public function saveCard(string $cardId): void
    {
        $cardData = $this->cards[$cardId] ?? null;

        if (!is_array($cardData)) {
            return;
        }

        $validator = Validator::make($cardData, [
            'front' => ['required', 'string'],
            'back' => ['required', 'string'],
            'tags' => ['nullable', 'string'],
            'deck_id' => ['required', 'exists:decks,id'],
        ]);

        if ($validator->fails()) {
            $this->addError('cards.'.$cardId, $validator->errors()->first());

            return;
        }

        $tags = $this->parseTags($cardData['tags'] ?? '');
        $extra = [
            'source_text' => $cardData['source_text'] ?? null,
            'page_index' => $cardData['page_index'] ?? null,
            'confidence' => $cardData['confidence'] ?? null,
        ];

        Card::query()
            ->where('id', $cardId)
            ->where('user_id', Auth::id())
            ->update([
                'front' => $cardData['front'],
                'back' => $cardData['back'],
                'tags' => $tags,
                'deck_id' => $cardData['deck_id'],
                'extra' => $extra,
            ]);

        $this->loadCards();
    }

    public function approveSelected(): void
    {
        $ids = $this->selectedIds();

        if ($ids === []) {
            return;
        }

        $deckIds = Deck::query()
            ->where('user_id', Auth::id())
            ->pluck('id')
            ->all();

        foreach ($ids as $cardId) {
            $cardData = $this->cards[$cardId] ?? null;
            if (!is_array($cardData)) {
                continue;
            }

            $deckId = $cardData['deck_id'] ?? null;
            if (!is_string($deckId) || !in_array($deckId, $deckIds, true)) {
                $this->addError('cards.'.$cardId, __('Please select a valid deck.'));

                return;
            }

            Card::query()
                ->where('id', $cardId)
                ->where('user_id', Auth::id())
                ->update(['deck_id' => $deckId]);
        }

        Card::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $ids)
            ->update(['status' => CardStatus::Approved]);

        $this->selected = [];
        $this->maybeCompleteJob();
        $this->loadCards();
    }

    public function rejectSelected(): void
    {
        $ids = $this->selectedIds();

        if ($ids === []) {
            return;
        }

        $deckIds = Card::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $ids)
            ->pluck('deck_id')
            ->unique()
            ->all();

        Card::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $ids)
            ->delete();

        $this->deleteEmptyDecks($deckIds);

        $this->selected = [];
        $this->maybeCompleteJob();
        $this->loadCards();
    }

    public function toggleSelectAll(): void
    {
        $allSelected = $this->areAllSelected();

        if ($allSelected) {
            $this->selected = [];
        } else {
            $this->selected = array_fill_keys(array_keys($this->cards), true);
        }
    }

    public function getSelectedAllProperty(): bool
    {
        return $this->areAllSelected();
    }

    private function areAllSelected(): bool
    {
        if (empty($this->cards)) {
            return false;
        }

        $cardIds = array_keys($this->cards);
        $selectedIds = array_keys(array_filter($this->selected));

        return count($cardIds) === count($selectedIds) && empty(array_diff($cardIds, $selectedIds));
    }

    private function loadCards(): void
    {
        $cards = Card::query()
            ->where('user_id', Auth::id())
            ->where('source_job_id', $this->jobId)
            ->where('status', CardStatus::Proposed)
            ->orderBy('created_at')
            ->get();

        $this->cards = $cards->mapWithKeys(function (Card $card): array {
            $tags = is_array($card->tags) ? $card->tags : [];
            $suggestedDeckId = data_get($card->extra, 'suggested_deck_id');

            return [
                $card->id => [
                    'front' => $card->front,
                    'back' => $card->back,
                    'tags' => implode(', ', $tags),
                    'deck_id' => $card->deck_id,
                    'suggested_deck_id' => $suggestedDeckId,
                    'source_text' => data_get($card->extra, 'source_text'),
                    'page_index' => data_get($card->extra, 'page_index'),
                    'confidence' => data_get($card->extra, 'confidence'),
                    'audio_path' => $card->audio_path,
                ],
            ];
        })->all();
    }

    private function maybeCompleteJob(): void
    {
        $remaining = Card::query()
            ->where('user_id', Auth::id())
            ->where('source_job_id', $this->jobId)
            ->where('status', CardStatus::Proposed)
            ->exists();

        if (! $remaining) {
            ExtractionJob::query()
                ->where('id', $this->jobId)
                ->where('user_id', Auth::id())
                ->update(['status' => ExtractionJobStatus::Completed]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function selectedIds(): array
    {
        return array_keys(array_filter($this->selected));
    }

    /**
     * @return array<int, string>
     */
    private function parseTags(string $tags): array
    {
        $parts = array_filter(array_map('trim', explode(',', $tags)));

        return array_values(array_unique($parts));
    }

    /**
     * @param array<int, string> $deckIds
     */
    private function deleteEmptyDecks(array $deckIds): void
    {
        if ($deckIds === []) {
            return;
        }

        Deck::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $deckIds)
            ->doesntHave('cards')
            ->delete();
    }
}
