<div class="flex min-w-0 flex-col gap-6 lg:flex-row">
        <section class="w-full lg:w-1/3">
            <div class="rounded-xl border border-neutral-200 p-4 lg:p-6 dark:border-neutral-700">
                <flux:heading size="lg">{{ __('Decks') }}</flux:heading>

                <div class="mt-4 flex flex-col gap-2">
                    <flux:button variant="{{ $selectedDeckId === null ? 'primary' : 'ghost' }}" wire:click="selectDeck(null)">
                        {{ __('All decks') }}
                    </flux:button>

                    @foreach ($decks as $deck)
                        @include('livewire.partials.deck-tree', ['deck' => $deck, 'level' => 0])
                    @endforeach
                </div>

                <div class="mt-6">
                    <flux:button variant="primary" wire:click="$set('showCreateDeckModal', true)">
                        {{ __('Create deck') }}
                    </flux:button>
                </div>
            </div>
        </section>

        <section class="w-full lg:w-2/3">
            <div class="rounded-xl border border-neutral-200 p-4 lg:p-6 dark:border-neutral-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4">
                    <div class="flex flex-col gap-3 lg:hidden">
                        <flux:input wire:model.debounce.500ms="search" :label="__('Search')" />
                        <flux:accordion>
                            <flux:accordion.item>
                                <flux:accordion.heading class="rounded-lg border-2 border-neutral-200 bg-neutral-50 px-4 py-3 font-medium transition hover:border-sprout-500 hover:bg-sprout-500/10 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-sprout-500 dark:hover:bg-sprout-500/15">
                                    <span class="inline-flex items-center gap-2">
                                        <flux:icon name="funnel" class="size-4 shrink-0 text-sprout-600 dark:text-sprout-400" />
                                        {{ __('More filters') }}
                                    </span>
                                </flux:accordion.heading>
                                <flux:accordion.content>
                                    <div class="flex flex-col gap-3 pt-1">
                                        <flux:input wire:model.debounce.500ms="tag" :label="__('Tag')" />
                                        <flux:select wire:model="status" :label="__('Status')">
                                            <option value="">{{ __('All') }}</option>
                                            <option value="proposed">{{ __('Proposed') }}</option>
                                            <option value="approved">{{ __('Approved') }}</option>
                                            <option value="archived">{{ __('Archived') }}</option>
                                        </flux:select>
                                        <flux:switch wire:model.live="showProposed" :label="__('Show proposed')" />
                                    </div>
                                </flux:accordion.content>
                            </flux:accordion.item>
                        </flux:accordion>
                    </div>
                    <div class="hidden flex-row flex-wrap items-end gap-4 lg:flex">
                        <flux:input wire:model.debounce.500ms="search" :label="__('Search')" />
                        <flux:input wire:model.debounce.500ms="tag" :label="__('Tag')" />
                        <flux:select wire:model="status" :label="__('Status')">
                            <option value="">{{ __('All') }}</option>
                            <option value="proposed">{{ __('Proposed') }}</option>
                            <option value="approved">{{ __('Approved') }}</option>
                            <option value="archived">{{ __('Archived') }}</option>
                        </flux:select>
                        <div class="flex items-end">
                            <flux:switch wire:model.live="showProposed" :label="__('Show proposed')" />
                        </div>
                    </div>
                </div>

                @if (count($selectedCards) > 0)
                    <div class="mt-4 flex flex-col gap-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-700 dark:bg-neutral-800">
                        <flux:text>
                            {{ __(':count card(s) selected', ['count' => count($selectedCards)]) }}
                        </flux:text>
                        <flux:button variant="danger" wire:click="$set('showDeleteModal', true)">
                            {{ __('Delete Selected') }}
                        </flux:button>
                    </div>
                @endif

                <div class="mt-6 space-y-4">
                    @if ($cards->count() > 0)
                        <div class="mb-4 flex items-center gap-2 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                            <flux:checkbox
                                :checked="$this->areAllCurrentPageCardsSelected"
                                :indeterminate="$this->isSomeCurrentPageCardsSelected && !$this->areAllCurrentPageCardsSelected"
                                wire:click="toggleSelectAll"
                                :label="__('Select all')"
                            />
                        </div>
                    @endif
                    @forelse ($cards as $card)
                        <div class="rounded-lg border border-neutral-200 p-3 sm:p-4 dark:border-neutral-700">
                            <div class="flex items-start gap-3">
                                <flux:checkbox
                                    wire:model.live="selectedCards"
                                    :value="$card->id"
                                    class="mt-1"
                                />
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <flux:heading size="sm">{{ $card->front }}</flux:heading>
                                        <flux:badge>{{ ucfirst($card->status->value) }}</flux:badge>
                                    </div>
                                    <flux:text class="mt-2 whitespace-pre-line">{{ $card->back }}</flux:text>
                                    @if ($card->audio_path)
                                        @php
                                            $audioUrl = URL::temporarySignedRoute('cards.audio', now()->addMinutes(10), ['card' => $card->id]);
                                        @endphp
                                        <div class="mt-2">
                                            <audio controls class="w-full max-w-md">
                                                <source src="{{ $audioUrl }}" type="audio/mpeg">
                                                {{ __('Your browser does not support the audio element.') }}
                                            </audio>
                                        </div>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach (($card->tags ?? []) as $tagItem)
                                            <flux:badge variant="secondary">#{{ $tagItem }}</flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <flux:text>{{ __('No cards found.') }}</flux:text>
                    @endforelse
                </div>

                <div class="mt-6">
                    {{ $cards->links() }}
                </div>
            </div>
        </section>

        <flux:modal wire:model.self="showDeleteModal" class="w-full max-w-[min(22rem,calc(100vw-2rem))]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete selected cards?') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('You are about to delete :count card(s).', ['count' => count($selectedCards)]) }}<br>
                        {{ __('This action cannot be reversed.') }}
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deleteSelected">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model.self="showCreateDeckModal" class="w-full max-w-[min(22rem,calc(100vw-2rem))]">
            <form wire:submit="createDeck" class="space-y-6">
                <flux:heading size="lg">{{ __('Create deck') }}</flux:heading>
                <div class="space-y-3">
                    <flux:input wire:model.defer="newDeckName" :label="__('Name')" required />
                    <flux:input wire:model.defer="newDeckDescription" :label="__('Description')" />
                    <flux:select wire:model.defer="newDeckParentId" :label="__('Parent deck')">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($decks as $deck)
                            <option value="{{ $deck['id'] }}">{{ $deck['name'] }}</option>
                            @foreach ($deck['children'] as $child)
                                <option value="{{ $child['id'] }}">— {{ $child['name'] }}</option>
                            @endforeach
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</div>
