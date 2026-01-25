<?php

namespace App\Livewire;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Deck;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class LibraryDashboard extends Component
{
    use WithPagination;

    public ?string $selectedDeckId = null;

    public string $search = '';

    public string $status = '';

    public string $tag = '';

    public bool $showProposed = false;

    public string $newDeckName = '';

    public string $newDeckDescription = '';

    public ?string $newDeckParentId = null;

    /**
     * @var array<string>
     */
    public array $selectedCards = [];

    public bool $showDeleteModal = false;

    public function render(): View
    {
        return view('livewire.library-dashboard', [
            'decks' => $this->deckTree(),
            'cards' => $this->cards(),
        ])->layout('layouts.app', ['title' => __('Library')]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedCards = [];
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->selectedCards = [];
    }

    public function updatedTag(): void
    {
        $this->resetPage();
        $this->selectedCards = [];
    }

    public function updatedShowProposed(): void
    {
        $this->resetPage();
        $this->selectedCards = [];
    }

    public function selectDeck(?string $deckId): void
    {
        $this->selectedDeckId = $deckId;
        $this->resetPage();
        $this->selectedCards = [];
    }

    public function toggleSelectAll(): void
    {
        $currentPageCardIds = $this->cards()->pluck('id')->toArray();

        if ($this->areAllCurrentPageCardsSelected()) {
            $this->selectedCards = array_values(array_diff($this->selectedCards, $currentPageCardIds));
        } else {
            $this->selectedCards = array_values(array_unique(array_merge($this->selectedCards, $currentPageCardIds)));
        }
    }

    public function deleteSelected(): void
    {
        if (empty($this->selectedCards)) {
            return;
        }

        $deckIds = Card::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $this->selectedCards)
            ->pluck('deck_id')
            ->unique()
            ->all();

        Card::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $this->selectedCards)
            ->delete();

        $this->deleteEmptyDecks($deckIds);

        $this->selectedCards = [];
        $this->showDeleteModal = false;
    }

    #[Computed]
    public function areAllCurrentPageCardsSelected(): bool
    {
        $currentPageCardIds = $this->cards()->pluck('id')->toArray();

        if (empty($currentPageCardIds)) {
            return false;
        }

        return count(array_intersect($this->selectedCards, $currentPageCardIds)) === count($currentPageCardIds);
    }

    #[Computed]
    public function isSomeCurrentPageCardsSelected(): bool
    {
        $currentPageCardIds = $this->cards()->pluck('id')->toArray();

        return !empty(array_intersect($this->selectedCards, $currentPageCardIds));
    }

    public function createDeck(): void
    {
        $validated = $this->validate([
            'newDeckName' => ['required', 'string', 'max:255'],
            'newDeckDescription' => ['nullable', 'string'],
            'newDeckParentId' => [
                'nullable',
                Rule::exists('decks', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        Deck::query()->create([
            'user_id' => Auth::id(),
            'parent_id' => $validated['newDeckParentId'],
            'name' => $validated['newDeckName'],
            'description' => $validated['newDeckDescription'],
        ]);

        $this->reset('newDeckName', 'newDeckDescription', 'newDeckParentId');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function deckTree(): array
    {
        $decks = Deck::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        $counts = Card::query()
            ->where('user_id', Auth::id())
            ->selectRaw('deck_id, COUNT(*) as total')
            ->groupBy('deck_id')
            ->pluck('total', 'deck_id');

        $nodes = [];

        foreach ($decks as $deck) {
            $nodes[$deck->id] = [
                'id' => $deck->id,
                'name' => $deck->name,
                'description' => $deck->description,
                'count' => (int) ($counts[$deck->id] ?? 0),
                'children' => [],
            ];
        }

        $tree = [];

        foreach ($decks as $deck) {
            if ($deck->parent_id && isset($nodes[$deck->parent_id])) {
                $nodes[$deck->parent_id]['children'][] = $nodes[$deck->id];
            } else {
                $tree[] = $nodes[$deck->id];
            }
        }

        return $tree;
    }

    private function cards(): LengthAwarePaginator
    {
        $query = Card::query()->where('user_id', Auth::id());

        if ($this->selectedDeckId) {
            $query->where('deck_id', $this->selectedDeckId);
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        } elseif (!$this->showProposed) {
            $query->where('status', '!=', CardStatus::Proposed->value);
        }

        if ($this->tag !== '') {
            $query->whereJsonContains('tags', $this->tag);
        }

        if ($this->search !== '') {
            $query->where(function ($builder) {
                $builder->where('front', 'like', '%'.$this->search.'%')
                    ->orWhere('back', 'like', '%'.$this->search.'%');
            });
        }

        return $query->latest()->paginate(20);
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
