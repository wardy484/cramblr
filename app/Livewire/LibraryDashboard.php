<?php

namespace App\Livewire;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Deck;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class LibraryDashboard extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?string $selectedDeckId = null;

    public string $search = '';

    public string $status = '';

    public string $tag = '';

    public bool $showProposed = false;

    public string $newDeckName = '';

    public string $newDeckDescription = '';

    public ?string $newDeckParentId = null;

    public bool $showCreateDeckModal = false;

    public bool $showRenameDeckModal = false;

    public ?string $deckToRename = null;

    public string $renameDeckName = '';

    public bool $showDeleteDeckModal = false;

    public ?string $deckToDelete = null;

    public bool $showEditCardModal = false;

    public ?string $editingCardId = null;

    public string $editCardFront = '';

    public string $editCardBack = '';

    public string $editCardTags = '';

    public string $editCardStatus = '';

    public ?string $editCardDeckId = null;

    /**
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $editCardAudio = null;

    public function render(): View
    {
        return view('livewire.library-dashboard', [
            'decks' => $this->deckTree(),
            'cards' => $this->cards(),
            'editingCard' => $this->editingCard(),
            'selectedDeck' => $this->selectedDeck(),
        ])->layout('layouts.app', ['title' => __('Library')]);
    }

    #[Computed]
    public function editingCard(): ?Card
    {
        if (! $this->editingCardId) {
            return null;
        }

        return Card::query()
            ->where('user_id', Auth::id())
            ->find($this->editingCardId);
    }

    #[Computed]
    public function selectedDeck(): ?Deck
    {
        if (! $this->selectedDeckId) {
            return null;
        }

        return Deck::query()
            ->where('user_id', Auth::id())
            ->find($this->selectedDeckId);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTag(): void
    {
        $this->resetPage();
    }

    public function updatedShowProposed(): void
    {
        $this->resetPage();
    }

    public function selectDeck(?string $deckId): void
    {
        $this->selectedDeckId = $deckId;
        $this->resetPage();
    }

    public function openRenameDeckModal(string $deckId): void
    {
        $deck = Deck::query()
            ->where('user_id', Auth::id())
            ->findOrFail($deckId);
        $this->deckToRename = $deck->id;
        $this->renameDeckName = $deck->name;
        $this->showRenameDeckModal = true;
    }

    public function renameDeck(): void
    {
        $validated = $this->validate([
            'deckToRename' => ['required', Rule::exists('decks', 'id')->where('user_id', Auth::id())],
            'renameDeckName' => ['required', 'string', 'max:255'],
        ]);

        Deck::query()
            ->where('user_id', Auth::id())
            ->whereKey($validated['deckToRename'])
            ->update(['name' => $validated['renameDeckName']]);

        $this->reset('deckToRename', 'renameDeckName', 'showRenameDeckModal');
    }

    public function openDeleteDeckModal(string $deckId): void
    {
        Deck::query()
            ->where('user_id', Auth::id())
            ->findOrFail($deckId);
        $this->deckToDelete = $deckId;
        $this->showDeleteDeckModal = true;
    }

    public function confirmDeleteDeck(): void
    {
        if (! $this->deckToDelete) {
            return;
        }

        $deck = Deck::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->deckToDelete);

        foreach ($deck->children as $child) {
            $this->deleteDeckRecursive($child);
        }

        $deck->cards()->delete();
        $deck->delete();

        if ($this->selectedDeckId === $this->deckToDelete) {
            $this->selectedDeckId = null;
        }

        $this->reset('deckToDelete', 'showDeleteDeckModal');
    }

    public function openEditCardModal(string $cardId): void
    {
        $card = Card::query()
            ->where('user_id', Auth::id())
            ->with('deck')
            ->findOrFail($cardId);

        $this->authorize('update', $card);

        $this->editingCardId = $card->id;
        $this->editCardFront = $card->front;
        $this->editCardBack = $card->back;
        $this->editCardTags = implode(', ', $card->tags ?? []);
        $this->editCardStatus = $card->status->value;
        $this->editCardDeckId = $card->deck_id;
        $this->editCardAudio = null;
        $this->showEditCardModal = true;
    }

    public function closeEditCardModal(): void
    {
        $this->reset(
            'showEditCardModal',
            'editingCardId',
            'editCardFront',
            'editCardBack',
            'editCardTags',
            'editCardStatus',
            'editCardDeckId',
            'editCardAudio'
        );
    }

    public function saveCard(): void
    {
        if (! $this->editingCardId) {
            return;
        }

        $card = Card::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->editingCardId);

        $this->authorize('update', $card);

        if ($this->editCardAudio && $card->audio_path !== null) {
            $this->addError('editCardAudio', __('Remove existing audio before uploading a new file.'));

            return;
        }

        $validated = $this->validate([
            'editCardFront' => ['required', 'string', 'max:65535'],
            'editCardBack' => ['required', 'string', 'max:65535'],
            'editCardTags' => ['nullable', 'string', 'max:1000'],
            'editCardStatus' => ['required', Rule::in(array_column(CardStatus::cases(), 'value'))],
            'editCardDeckId' => ['required', Rule::exists('decks', 'id')->where('user_id', Auth::id())],
            'editCardAudio' => ['nullable', 'file', 'mimes:mp3,mpeg,wav,ogg,m4a', 'max:10240'],
        ]);

        $tags = array_values(array_filter(array_map('trim', explode(',', $validated['editCardTags'] ?? ''))));

        $card->update([
            'front' => $validated['editCardFront'],
            'back' => $validated['editCardBack'],
            'tags' => $tags,
            'status' => CardStatus::from($validated['editCardStatus']),
            'deck_id' => $validated['editCardDeckId'],
        ]);

        if ($this->editCardAudio) {
            if ($card->audio_path && Storage::disk('private')->exists($card->audio_path)) {
                Storage::disk('private')->delete($card->audio_path);
            }
            $path = $this->editCardAudio->storeAs(
                'cards/'.$card->id,
                Str::uuid()->toString().'.'.$this->editCardAudio->extension(),
                'private'
            );
            $card->update(['audio_path' => $path]);
        }

        $this->reset(
            'showEditCardModal',
            'editingCardId',
            'editCardFront',
            'editCardBack',
            'editCardTags',
            'editCardStatus',
            'editCardDeckId',
            'editCardAudio'
        );
    }

    public function removeCardAudio(string $cardId): void
    {
        $card = Card::query()
            ->where('user_id', Auth::id())
            ->findOrFail($cardId);

        $this->authorize('update', $card);

        if ($card->audio_path && Storage::disk('private')->exists($card->audio_path)) {
            Storage::disk('private')->delete($card->audio_path);
        }

        $card->update(['audio_path' => null]);

        if ($this->editingCardId === $cardId) {
            $this->editCardAudio = null;
        }
    }

    private function deleteDeckRecursive(Deck $deck): void
    {
        foreach ($deck->children as $child) {
            $this->deleteDeckRecursive($child);
        }
        $deck->cards()->delete();
        $deck->delete();
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

        $this->reset('newDeckName', 'newDeckDescription', 'newDeckParentId', 'showCreateDeckModal');
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
        } elseif (! $this->showProposed) {
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
}
