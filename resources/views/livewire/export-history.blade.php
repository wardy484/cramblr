<div class="mx-auto flex min-w-0 w-full max-w-4xl flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ __('Export to Anki') }}</flux:heading>
            <form wire:submit="createExport" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
                <flux:select wire:model.defer="deckId" :label="__('Deck')">
                    <option value="">{{ __('Select a deck') }}</option>
                    @foreach ($decks as $deck)
                        <option value="{{ $deck->id }}">{{ $deck->name }}</option>
                    @endforeach
                </flux:select>
                <flux:button variant="primary" type="submit">{{ __('Export to Anki') }}</flux:button>
            </form>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <flux:heading size="sm">{{ __('Export history') }}</flux:heading>
            <div class="mt-4 space-y-4">
                @forelse ($exports as $export)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <div>
                            <flux:text>{{ $export->deck?->name ?? __('Unknown deck') }}</flux:text>
                            <flux:text class="text-sm text-neutral-500">{{ ucfirst($export->status->value) }}</flux:text>
                            @if ($export->error_message)
                                <flux:text class="text-sm text-red-600">{{ $export->error_message }}</flux:text>
                            @endif
                        </div>
                        @if ($export->status->value === 'ready')
                            @php
                                $downloadUrl = URL::temporarySignedRoute(
                                    'exports.download',
                                    now()->addMinutes(10),
                                    ['export' => $export->id]
                                );
                            @endphp
                            <flux:button href="{{ $downloadUrl }}" variant="primary">
                                {{ __('Download .apkg') }}
                            </flux:button>
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('No exports yet.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</div>
