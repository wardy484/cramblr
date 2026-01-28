<div class="mx-auto flex min-w-0 w-full max-w-5xl flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <flux:heading size="lg">{{ __('Review proposed cards') }}</flux:heading>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:click="toggleSelectAll" {{ $this->selectedAll ? 'checked' : '' }} />
                        <flux:text class="text-sm">{{ __('Select all') }}</flux:text>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:gap-2">
                    <flux:button variant="primary" wire:click="approveSelected">{{ __('Approve selected') }}</flux:button>
                    <flux:button variant="danger" wire:click="rejectSelected">{{ __('Reject selected') }}</flux:button>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            @forelse ($cards as $cardId => $card)
                @php
                    $pageId = $pageMap[$card['page_index'] ?? null] ?? null;
                    $imageUrl = $pageId
                        ? URL::temporarySignedRoute('job-pages.image', now()->addMinutes(10), ['job' => $jobId, 'page' => $pageId])
                        : null;
                @endphp
                <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="selected.{{ $cardId }}" />
                            <flux:text class="text-sm text-neutral-500">
                                {{ __('Confidence') }}: {{ number_format((float) ($card['confidence'] ?? 0), 2) }}
                            </flux:text>
                        </div>
                        <flux:text class="text-sm text-neutral-500">
                            {{ __('Page') }} {{ $card['page_index'] ?? '-' }}
                        </flux:text>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-[120px,1fr]">
                        <div>
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="Page {{ $card['page_index'] }}" class="h-24 w-24 rounded object-cover" />
                            @else
                                <div class="flex h-24 w-24 items-center justify-center rounded bg-neutral-100 text-xs text-neutral-500 dark:bg-neutral-800">
                                    {{ __('No image') }}
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4">
                            <flux:input wire:model.defer="cards.{{ $cardId }}.front" :label="__('Front')" />
                            <flux:textarea wire:model.defer="cards.{{ $cardId }}.back" :label="__('Back')" rows="3" />
                            @if (!empty($card['audio_path']))
                                @php
                                    $audioUrl = URL::temporarySignedRoute('cards.audio', now()->addMinutes(10), ['card' => $cardId]);
                                @endphp
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                                        {{ __('Audio') }}
                                    </flux:text>
                                    <audio controls class="mt-2 w-full">
                                        <source src="{{ $audioUrl }}" type="audio/mpeg">
                                        {{ __('Your browser does not support the audio element.') }}
                                    </audio>
                                </div>
                            @endif
                            <flux:input wire:model.defer="cards.{{ $cardId }}.tags" :label="__('Tags (comma separated)')" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:select wire:model.defer="cards.{{ $cardId }}.deck_id" :label="__('Deck')">
                                        @foreach ($decks as $deck)
                                            <option value="{{ $deck->id }}">{{ $deck->name }}</option>
                                        @endforeach
                                    </flux:select>
                                    @if (!empty($card['suggested_deck_id']))
                                        @php
                                            $suggestedDeck = $decks->firstWhere('id', $card['suggested_deck_id']);
                                        @endphp
                                        @if ($suggestedDeck)
                                            <flux:badge variant="info" class="mt-6">
                                                {{ __('AI suggestion') }}: {{ $suggestedDeck->name }}
                                            </flux:badge>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <flux:text class="text-sm text-neutral-500">
                                {{ __('Source') }}: {{ $card['source_text'] ?? '-' }}
                            </flux:text>
                            <div>
                                <flux:button variant="primary" wire:click="saveCard('{{ $cardId }}')">
                                    {{ __('Save card') }}
                                </flux:button>
                                @error('cards.'.$cardId)
                                    <flux:text class="mt-2 text-sm text-red-600">{{ $message }}</flux:text>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 text-center dark:border-neutral-700">
                    <flux:text>{{ __('No proposed cards to review.') }}</flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>
