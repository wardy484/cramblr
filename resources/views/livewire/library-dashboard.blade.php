<div class="flex min-w-0 flex-col gap-6 lg:flex-row">
        <section class="w-full lg:w-1/3">
            <div class="rounded-xl border border-neutral-200 p-4 lg:p-6 dark:border-neutral-700">
                <flux:heading size="lg">{{ __('Decks') }}</flux:heading>

                <div class="mt-4 flex flex-col gap-2">
                    <button
                        type="button"
                        wire:click="selectDeck(null)"
                        @class([
                            'flex items-center justify-between rounded-lg px-3 py-2 text-left transition',
                            'bg-sprout-500/10 text-sprout-700 hover:bg-sprout-500/15 dark:bg-sprout-500/15 dark:text-sprout-200 dark:hover:bg-sprout-500/20' => $selectedDeckId === null,
                            'text-neutral-900 hover:bg-neutral-100 dark:text-neutral-100 dark:hover:bg-neutral-800' => $selectedDeckId !== null,
                        ])
                        @if ($selectedDeckId === null) aria-current="true" @endif
                    >
                        <span>{{ __('All decks') }}</span>
                    </button>

                    @foreach ($decks as $deck)
                        @include('livewire.partials.deck-tree', ['deck' => $deck, 'level' => 0, 'selectedDeckId' => $selectedDeckId])
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
                <div class="flex items-center justify-between gap-3">
                    <flux:text class="text-sm text-neutral-500">
                        <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ __('Deck') }}:</span>
                        <span class="ml-1 text-neutral-600 dark:text-neutral-300">{{ $selectedDeck?->name ?? __('All decks') }}</span>
                    </flux:text>
                    @if ($selectedDeck)
                        <flux:button variant="ghost" size="xs" wire:click="selectDeck(null)">
                            {{ __('Clear') }}
                        </flux:button>
                    @endif
                </div>

                <div class="mt-3 flex flex-col gap-3 lg:mt-4 lg:flex-row lg:items-end lg:gap-4">
                    <div class="flex flex-col gap-3 lg:hidden">
                        <flux:input wire:model.debounce.500ms="search" :label="__('Search')" />
                        @php
                            $activeFilters = 0;
                            if ($tag !== '') {
                                $activeFilters++;
                            }
                            if ($status !== '') {
                                $activeFilters++;
                            }
                            if ($showProposed) {
                                $activeFilters++;
                            }
                        @endphp
                        <flux:accordion transition>
                            <flux:accordion.item>
                                <flux:accordion.heading class="flex items-center justify-between rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800">
                                    <span class="flex items-center gap-2">
                                        <flux:icon name="funnel" class="size-4 text-neutral-500 dark:text-neutral-400" />
                                        <span>{{ __('Filters') }}</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        @if ($activeFilters > 0)
                                            <flux:badge variant="secondary" size="sm">{{ $activeFilters }}</flux:badge>
                                        @endif
                                    </span>
                                </flux:accordion.heading>
                                <flux:accordion.content>
                                    <flux:card size="sm" class="mt-2 space-y-3 bg-neutral-50 dark:bg-neutral-800">
                                        <flux:input wire:model.debounce.500ms="tag" :label="__('Tag')" />
                                        <flux:select wire:model="status" :label="__('Status')">
                                            <option value="">{{ __('All') }}</option>
                                            <option value="proposed">{{ __('Proposed') }}</option>
                                            <option value="approved">{{ __('Approved') }}</option>
                                            <option value="archived">{{ __('Archived') }}</option>
                                        </flux:select>
                                        <flux:switch wire:model.live="showProposed" :label="__('Show proposed')" />
                                    </flux:card>
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

                <div class="mt-6 space-y-4">
                    @forelse ($cards as $card)
                        <div wire:key="card-{{ $card->id }}" class="rounded-lg border border-neutral-200 p-3 sm:p-4 dark:border-neutral-700">
                            <div class="flex items-start gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <flux:heading size="sm">{{ $card->front }}</flux:heading>
                                        <div class="flex items-center gap-2">
                                            <flux:badge>{{ ucfirst($card->status->value) }}</flux:badge>
                                            <flux:button variant="ghost" size="sm" wire:click="openEditCardModal('{{ $card->id }}')" aria-label="{{ __('Edit card') }}">
                                                <flux:icon name="pencil" class="size-4" />
                                            </flux:button>
                                        </div>
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

        <flux:modal wire:model.self="showRenameDeckModal" class="w-full max-w-[min(22rem,calc(100vw-2rem))]">
            <form wire:submit="renameDeck" class="space-y-6">
                <flux:heading size="lg">{{ __('Rename deck') }}</flux:heading>
                <flux:input wire:model="renameDeckName" :label="__('Name')" required />
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Rename') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal wire:model.self="showDeleteDeckModal" class="w-full max-w-[min(22rem,calc(100vw-2rem))]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete deck?') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('This deck and all its cards will be permanently deleted.') }}
                        {{ __('This action cannot be reversed.') }}
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="confirmDeleteDeck">
                        {{ __('Delete deck') }}
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

        <flux:modal wire:model.self="showEditCardModal" class="w-full max-w-[min(32rem,calc(100vw-2rem))]">
            <form wire:submit="saveCard" class="space-y-6">
                <flux:heading size="lg">{{ __('Edit card') }}</flux:heading>
                <div class="space-y-4">
                    <flux:input wire:model="editCardFront" :label="__('Front')" required />
                    <flux:textarea wire:model="editCardBack" :label="__('Back')" required rows="3" />
                    <flux:input wire:model="editCardTags" :label="__('Tags (comma-separated)')" />
                    <flux:select wire:model="editCardStatus" :label="__('Status')">
                        <option value="proposed">{{ __('Proposed') }}</option>
                        <option value="approved">{{ __('Approved') }}</option>
                        <option value="archived">{{ __('Archived') }}</option>
                    </flux:select>
                    <flux:select wire:model="editCardDeckId" :label="__('Deck')">
                        @foreach ($decks as $deck)
                            <option value="{{ $deck['id'] }}">{{ $deck['name'] }}</option>
                            @foreach ($deck['children'] as $child)
                                <option value="{{ $child['id'] }}">— {{ $child['name'] }}</option>
                            @endforeach
                        @endforeach
                    </flux:select>
                    <div>
                        <flux:heading size="sm" class="mb-2">{{ __('Audio') }}</flux:heading>
                        @if ($editingCard?->audio_path)
                            <div class="flex items-center gap-3">
                                <audio controls class="w-full max-w-md">
                                    <source src="{{ URL::temporarySignedRoute('cards.audio', now()->addMinutes(10), ['card' => $editingCard->id]) }}" type="audio/mpeg">
                                </audio>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    class="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10"
                                    aria-label="{{ __('Remove audio') }}"
                                    wire:click="removeCardAudio('{{ $editingCard->id }}')"
                                >
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>
                        @else
                            @if ($editCardAudio)
                                <div class="flex flex-col gap-2">
                                    <flux:text class="text-sm">{{ $editCardAudio->getClientOriginalName() }}</flux:text>
                                    <div>
                                        <flux:button type="button" variant="ghost" size="sm" wire:click="$set('editCardAudio', null)">
                                            {{ __('Clear selection') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endif

                            <flux:file-upload wire:model="editCardAudio" :label="__('Upload audio')">
                                <flux:file-upload.dropzone :heading="__('Drop file or click to browse')" :text="__('MP3, WAV, OGG, M4A up to 10MB')" />
                            </flux:file-upload>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close wire:click="closeEditCardModal">
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</div>
