<div class="flex flex-col gap-6 lg:flex-row">
        <section class="w-full lg:w-1/3">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
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
                    <flux:heading size="sm">{{ __('Create deck') }}</flux:heading>
                    <form wire:submit="createDeck" class="mt-3 space-y-3">
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
                        <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                    </form>
                </div>
            </div>
        </section>

        <section class="w-full lg:w-2/3">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
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

                @if (count($selectedCards) > 0)
                    <div class="mt-4 flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800">
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
                        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
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

        <flux:modal wire:model.self="showDeleteModal" class="min-w-[22rem]">
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
    </div>
</div>
