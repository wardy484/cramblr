<div class="flex flex-col gap-2" style="padding-left: {{ $level * 16 }}px;">
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            wire:click="selectDeck('{{ $deck['id'] }}')"
            @class([
                'flex flex-1 items-center justify-between rounded-lg px-3 py-2 text-left transition',
                'bg-sprout-500/10 text-sprout-700 hover:bg-sprout-500/15 dark:bg-sprout-500/15 dark:text-sprout-200 dark:hover:bg-sprout-500/20' => ($selectedDeckId ?? null) === $deck['id'],
                'text-neutral-900 hover:bg-neutral-100 dark:text-neutral-100 dark:hover:bg-neutral-800' => ($selectedDeckId ?? null) !== $deck['id'],
            ])
            @if (($selectedDeckId ?? null) === $deck['id']) aria-current="true" @endif
        >
            <span>{{ $deck['name'] }}</span>
            <span class="text-xs text-neutral-500">{{ $deck['count'] }}</span>
        </button>
        <div class="flex items-center gap-1">
            <a href="{{ route('decks.study', $deck['id']) }}" class="rounded-lg border border-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                {{ __('Study') }}
            </a>
            <a href="{{ route('decks.learn', $deck['id']) }}" class="rounded-lg border border-neutral-200 px-2 py-1 text-xs text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                {{ __('Learn') }}
            </a>
            <flux:dropdown align="end">
                <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" square aria-label="{{ __('Deck options') }}" />
                <flux:menu>
                    <flux:menu.item icon="pencil" wire:click="openRenameDeckModal('{{ $deck['id'] }}')">
                        {{ __('Rename') }}
                    </flux:menu.item>
                    <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteDeckModal('{{ $deck['id'] }}')">
                        {{ __('Delete deck') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @foreach ($deck['children'] as $child)
        @include('livewire.partials.deck-tree', ['deck' => $child, 'level' => $level + 1, 'selectedDeckId' => $selectedDeckId ?? null])
    @endforeach
</div>
