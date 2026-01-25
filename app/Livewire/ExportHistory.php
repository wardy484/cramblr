<?php

namespace App\Livewire;

use App\Enums\ExportStatus;
use App\Jobs\ExportDeckApkg;
use App\Models\Deck;
use App\Models\Export;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class ExportHistory extends Component
{
    public ?string $deckId = null;

    public function render(): View
    {
        return view('livewire.export-history', [
            'decks' => Deck::query()
                ->where('user_id', Auth::id())
                ->orderBy('name')
                ->get(),
            'exports' => Export::query()
                ->with('deck')
                ->where('user_id', Auth::id())
                ->latest()
                ->get(),
        ])->layout('layouts.app', ['title' => __('Exports')]);
    }

    public function createExport(): void
    {
        $validated = $this->validate([
            'deckId' => [
                'required',
                Rule::exists('decks', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        $export = Export::query()->create([
            'user_id' => Auth::id(),
            'deck_id' => $validated['deckId'],
            'status' => ExportStatus::Queued,
        ]);

        ExportDeckApkg::dispatch($export->id);

        $this->deckId = null;
    }
}
