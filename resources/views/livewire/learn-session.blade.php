<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">{{ __('Learn') }} {{ $deckName }}</flux:heading>
            <flux:badge variant="secondary">
                {{ __('Remaining') }}: {{ count($queue) }}
            </flux:badge>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="ghost" wire:click="$set('showAiPanel', true)">{{ __('AI Assist') }}</flux:button>
        </div>
    </div>

    @if ($this->currentCard)
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <button type="button" wire:click="flip" class="flex w-full flex-col gap-3 text-left">
                <flux:text class="text-xs uppercase text-neutral-500">
                    {{ $showBack ? __('Back') : __('Front') }}
                </flux:text>
                <flux:heading size="lg">{{ $showBack ? $this->backText : $this->frontText }}</flux:heading>
                @if (! $showBack)
                    <flux:text class="text-sm text-neutral-500">{{ __('Tap to reveal answer') }}</flux:text>
                @endif
            </button>

            @if ($showBack && $this->frontText && $this->backText)
                <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <flux:text class="text-xs uppercase text-neutral-500">{{ __('Front') }}</flux:text>
                    <flux:text class="mt-2">{{ $this->frontText }}</flux:text>
                </div>
            @endif

            @if ($this->audioUrl)
                <div class="mt-4">
                    <flux:text class="text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ __('Audio') }}</flux:text>
                    <audio controls class="mt-2 w-full" @if ($settings['auto_play_audio'] && ! $settings['muted']) autoplay @endif>
                        <source src="{{ $this->audioUrl }}" type="audio/mpeg">
                        {{ __('Your browser does not support the audio element.') }}
                    </audio>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:text class="text-sm text-neutral-500">{{ __('Check your understanding') }}</flux:text>
            <div class="mt-4 flex flex-wrap gap-3">
                <flux:button variant="primary" wire:click="markLearned">{{ __('I got it') }}</flux:button>
                <flux:button variant="outline" wire:click="requestAssist('explain')">{{ __('Explain it') }}</flux:button>
                <flux:button variant="outline" wire:click="requestAssist('mnemonic')">{{ __('Give mnemonic') }}</flux:button>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-neutral-200 p-8 text-center dark:border-neutral-700">
            <flux:heading size="md">{{ __('No new cards to learn') }}</flux:heading>
            <flux:text class="mt-2 text-neutral-500">
                {{ __('Pick another deck or switch to Study mode for reviews.') }}
            </flux:text>
        </div>
    @endif

    <flux:modal wire:model.self="showAiPanel" variant="floating" class="min-w-[22rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="md">{{ __('AI Study Assist') }}</flux:heading>
                <flux:text class="mt-2 text-sm text-neutral-500">
                    {{ __('Need extra help? Ask for explanations, mnemonics, or examples.') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button variant="outline" wire:click="requestAssist('explain')">{{ __('Explain') }}</flux:button>
                <flux:button variant="outline" wire:click="requestAssist('mnemonic')">{{ __('Mnemonic') }}</flux:button>
                <flux:button variant="outline" wire:click="requestAssist('example')">{{ __('Example') }}</flux:button>
            </div>
            @php
                $aiKey = $this->currentCard ? $this->currentCard->id.'-'.$aiMode : null;
            @endphp
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                <div wire:loading wire:target="requestAssist" class="text-neutral-500">
                    {{ __('Thinking...') }}
                </div>
                <div wire:loading.remove wire:target="requestAssist">
                    {{ $aiKey && isset($aiResponses[$aiKey]) ? $aiResponses[$aiKey] : __('Choose an assist option to get started.') }}
                </div>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
