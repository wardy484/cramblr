<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div class="rounded-2xl border border-neutral-200 bg-gradient-to-br from-white via-white to-emerald-50/60 p-6 dark:border-neutral-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-900/20">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Next up') }}</flux:heading>
                <flux:text class="text-sm text-neutral-500">
                    {{ __('Your recommended study queue based on what is due and new.') }}
                </flux:text>
            </div>
            <flux:button variant="primary" href="{{ route('library') }}">
                {{ __('Go to library') }}
            </flux:button>
        </div>
    </div>

    @if ($this->recommended)
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <flux:heading size="md">{{ __('Recommended now') }}</flux:heading>
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('Recommended') }}: {{ $this->recommended['deck_name'] }}
                    </flux:text>
                    <flux:text class="text-sm text-neutral-500">
                        @if ($this->recommended['type'] === 'review')
                            {{ __(':count cards due', ['count' => $this->recommended['count']]) }}
                        @else
                            {{ __(':count new cards', ['count' => $this->recommended['count']]) }}
                        @endif
                    </flux:text>
                    @if ($this->recentDeck)
                        <flux:text class="text-sm text-neutral-500">
                            {{ __('Recently studied') }}: {{ $this->recentDeck['deck_name'] }}
                            • {{ $this->recentDeck['reviewed_at']->diffForHumans() }}
                        </flux:text>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($this->recommended['type'] === 'review')
                        <flux:button variant="primary" href="{{ route('decks.study', $this->recommended['deck_id']) }}">
                            {{ __('Start review') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" href="{{ route('decks.learn', $this->recommended['deck_id']) }}">
                            {{ __('Start learn') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-neutral-200 p-8 text-center dark:border-neutral-700">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                <flux:icon name="sparkles" />
            </div>
            <flux:heading size="md" class="mt-4">{{ __('All caught up!') }}</flux:heading>
            <flux:text class="mt-2 text-neutral-500">
                {{ __('No new or due cards right now. Add cards in the library or check back later.') }}
            </flux:text>
            <flux:button class="mt-4" variant="outline" href="{{ route('library') }}">
                {{ __('Add cards') }}
            </flux:button>
        </div>
    @endif

    <section class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="md">{{ __('Recent study history') }}</flux:heading>
            <flux:badge variant="secondary">{{ count($this->recentHistory) }}</flux:badge>
        </div>
        <div class="mt-4 flex flex-col gap-3">
            @forelse ($this->recentHistory as $history)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" wire:key="history-{{ $history['deck_id'] }}">
                    <div class="space-y-1">
                        <flux:heading size="sm">{{ $history['deck_name'] }}</flux:heading>
                        <flux:text class="text-sm text-neutral-500">
                            {{ __('Last studied') }}: {{ $history['reviewed_at']->diffForHumans() }}
                        </flux:text>
                        <flux:text class="text-sm text-neutral-500">
                            {{ __(':count reviews', ['count' => $history['review_count']]) }}
                        </flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="ghost" href="{{ route('decks.study', $history['deck_id']) }}">
                            {{ __('Review') }}
                        </flux:button>
                        <flux:button variant="outline" href="{{ route('decks.study', ['deck' => $history['deck_id'], 'recap' => 1]) }}">
                            {{ __('Recap') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <flux:text class="text-sm text-neutral-500">
                    {{ __('No study history yet. Start a session to see it here.') }}
                </flux:text>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="md">{{ __('Review') }}</flux:heading>
                <flux:badge variant="secondary">{{ count($this->reviewDecks) }}</flux:badge>
            </div>
            <div class="mt-4 flex flex-col gap-3">
                @forelse ($this->reviewDecks as $deck)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" wire:key="review-{{ $deck['deck_id'] }}">
                        <div class="space-y-1">
                            <flux:heading size="sm">{{ $deck['deck_name'] }}</flux:heading>
                            <flux:text class="text-sm text-neutral-500">
                                {{ __(':count cards due', ['count' => $deck['review_due']]) }}
                            </flux:text>
                        </div>
                        <flux:button variant="outline" href="{{ route('decks.study', $deck['deck_id']) }}">
                            {{ __('Review') }}
                        </flux:button>
                    </div>
                @empty
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('No reviews due right now.') }}
                    </flux:text>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="md">{{ __('Learn') }}</flux:heading>
                <flux:badge variant="secondary">{{ count($this->learnDecks) }}</flux:badge>
            </div>
            <div class="mt-4 flex flex-col gap-3">
                @forelse ($this->learnDecks as $deck)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" wire:key="learn-{{ $deck['deck_id'] }}">
                        <div class="space-y-1">
                            <flux:heading size="sm">{{ $deck['deck_name'] }}</flux:heading>
                            <flux:text class="text-sm text-neutral-500">
                                {{ __(':count new cards', ['count' => $deck['new_count']]) }}
                            </flux:text>
                        </div>
                        <flux:button variant="outline" href="{{ route('decks.learn', $deck['deck_id']) }}">
                            {{ __('Learn') }}
                        </flux:button>
                    </div>
                @empty
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('No new cards waiting in your decks.') }}
                    </flux:text>
                @endforelse
            </div>
        </section>
    </div>
</div>
