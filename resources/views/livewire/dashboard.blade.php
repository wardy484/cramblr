<div class="mx-auto flex min-w-0 w-full max-w-5xl flex-col gap-6">
    <section class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700 sm:p-6">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Next up') }}</flux:heading>
                <flux:text class="text-sm text-neutral-500">
                    {{ __('Pick what to study—recommended first, or choose another option.') }}
                </flux:text>
            </div>
        </div>
        @if (count($this->nextUpOptions) > 0)
            <ul class="flex flex-col gap-3" role="list">
                @foreach ($this->nextUpOptions as $option)
                    <li wire:key="next-up-{{ $option['deck_id'] }}">
                        <flux:card size="sm" class="border-l-4 {{ $option['is_recommended'] ? 'border-l-emerald-500' : 'border-l-neutral-300 dark:border-l-neutral-600' }} transition hover:border-neutral-300 dark:hover:bg-white/5 dark:hover:border-neutral-600">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading size="sm" class="font-medium">{{ $option['deck_name'] }}</flux:heading>
                                        @if ($option['is_recommended'])
                                            <flux:badge variant="primary" size="sm">{{ __('Recommended') }}</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text class="mt-0.5 block text-sm text-neutral-500 dark:text-neutral-400">
                                        @if ($option['type'] === 'review')
                                            {{ __(':count cards due', ['count' => $option['count']]) }}
                                        @else
                                            {{ __(':count new cards', ['count' => $option['count']]) }}
                                        @endif
                                    </flux:text>
                                </div>
                                <div class="flex w-full shrink-0 justify-end gap-2 sm:w-auto">
                                    @if ($option['type'] === 'review')
                                        <flux:button variant="{{ $option['is_recommended'] ? 'primary' : 'outline' }}" size="sm" href="{{ route('decks.study', $option['deck_id']) }}">
                                            {{ __('Review') }}
                                        </flux:button>
                                    @else
                                        <flux:button variant="{{ $option['is_recommended'] ? 'primary' : 'outline' }}" size="sm" href="{{ route('decks.learn', $option['deck_id']) }}">
                                            {{ __('Learn') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </flux:card>
                    </li>
                @endforeach
            </ul>
        @else
            <flux:card size="sm" class="border border-dashed border-neutral-300 py-8 text-center dark:border-neutral-600">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                    <flux:icon name="sparkles" />
                </div>
                <flux:heading size="md" class="mt-4">{{ __('All caught up!') }}</flux:heading>
                <flux:text class="mt-2 block text-sm text-neutral-500">
                    {{ __('No new or due cards right now. Add cards in the library or check back later.') }}
                </flux:text>
                <flux:button class="mt-4" variant="outline" href="{{ route('library') }}">
                    {{ __('Add cards') }}
                </flux:button>
            </flux:card>
        @endif
    </section>

    <section class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700 sm:p-6">
        <div class="mb-5 flex items-center justify-between gap-3">
            <flux:heading size="md">{{ __('Recent study history') }}</flux:heading>
            <flux:badge variant="secondary">{{ count($this->recentHistory) }}</flux:badge>
        </div>
        <ul class="flex flex-col gap-3" role="list">
            @forelse ($this->recentHistory as $history)
                <li wire:key="history-{{ $history['deck_id'] }}">
                    <flux:card size="sm" class="border-l-4 border-l-amber-500 transition hover:border-neutral-300 dark:hover:bg-white/5 dark:hover:border-neutral-600">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div class="min-w-0">
                                <flux:heading size="sm" class="font-medium">{{ $history['deck_name'] }}</flux:heading>
                                <flux:text class="mt-0.5 block text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ $history['reviewed_at']->diffForHumans() }}
                                    <span class="text-neutral-400 dark:text-neutral-500">·</span>
                                    {{ __(':count reviews', ['count' => $history['review_count']]) }}
                                </flux:text>
                            </div>
                            <div class="flex w-full shrink-0 justify-end gap-2 sm:w-auto">
                                <flux:button variant="ghost" size="sm" href="{{ route('decks.study', $history['deck_id']) }}">
                                    {{ __('Review') }}
                                </flux:button>
                                <flux:button variant="outline" size="sm" href="{{ route('decks.study', ['deck' => $history['deck_id'], 'recap' => 1]) }}">
                                    {{ __('Recap') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>
                </li>
            @empty
                <flux:card size="sm" class="border border-dashed border-neutral-300 py-8 text-center dark:border-neutral-600">
                    <flux:icon name="book-open-text" class="mx-auto size-9 text-neutral-300 dark:text-neutral-600" />
                    <flux:text class="mt-2 block text-sm text-neutral-500">
                        {{ __('No study history yet. Start a session to see it here.') }}
                    </flux:text>
                </flux:card>
            @endforelse
        </ul>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="md">{{ __('Review') }}</flux:heading>
                <flux:badge variant="secondary">{{ count($this->reviewDecks) }}</flux:badge>
            </div>
            <div class="mt-4 flex flex-col gap-3">
                @forelse ($this->reviewDecks as $deck)
                    <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:p-4 dark:border-neutral-700" wire:key="review-{{ $deck['deck_id'] }}">
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

        <section class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="md">{{ __('Learn') }}</flux:heading>
                <flux:badge variant="secondary">{{ count($this->learnDecks) }}</flux:badge>
            </div>
            <div class="mt-4 flex flex-col gap-3">
                @forelse ($this->learnDecks as $deck)
                    <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:p-4 dark:border-neutral-700" wire:key="learn-{{ $deck['deck_id'] }}">
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
