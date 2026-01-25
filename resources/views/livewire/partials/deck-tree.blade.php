<div class="flex flex-col gap-2" style="padding-left: {{ $level * 16 }}px;">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="selectDeck('{{ $deck['id'] }}')" class="flex flex-1 items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-neutral-100 dark:hover:bg-neutral-800">
            <span>{{ $deck['name'] }}</span>
            <span class="text-xs text-neutral-500">{{ $deck['count'] }}</span>
        </button>
        <div class="flex gap-2">
            <a href="{{ route('decks.study', $deck['id']) }}" class="rounded-lg border border-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                {{ __('Study') }}
            </a>
            <a href="{{ route('decks.learn', $deck['id']) }}" class="rounded-lg border border-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                {{ __('Learn') }}
            </a>
        </div>
    </div>

    @foreach ($deck['children'] as $child)
        @include('livewire.partials.deck-tree', ['deck' => $child, 'level' => $level + 1])
    @endforeach
</div>
