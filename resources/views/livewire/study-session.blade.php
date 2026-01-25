<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">{{ __('Study') }} {{ $deckName }}</flux:heading>
            <flux:badge variant="secondary">
                {{ __('Remaining') }}: {{ count($queue) }}
            </flux:badge>
            @if ($recap)
                <flux:badge variant="outline">{{ __('Recap') }}</flux:badge>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="ghost" wire:click="toggleSettings">{{ __('Settings') }}</flux:button>
            <flux:button variant="ghost" wire:click="$set('showAiPanel', true)">{{ __('AI Assist') }}</flux:button>
                <flux:button variant="ghost" wire:click="extendSession">{{ __('Extend session') }}</flux:button>
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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('Rate your recall') }}
                    </flux:text>
                    @if ($this->currentStepInfo)
                        <flux:badge variant="secondary">
                            {{ __('Step :current of :total', ['current' => $this->currentStepInfo['current'], 'total' => $this->currentStepInfo['total']]) }}
                        </flux:badge>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($this->predictedNextInterval)
                        <flux:text class="text-sm text-neutral-500">
                            {{ __('Next') }}: {{ $this->predictedNextInterval }}
                        </flux:text>
                    @endif
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('Mode') }}: {{ strtoupper($settings['algorithm']) }}
                    </flux:text>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <flux:button variant="danger" wire:click="rate('again')">{{ __('Again') }}</flux:button>
                <flux:button variant="outline" wire:click="rate('hard')">{{ __('Hard') }}</flux:button>
                <flux:button variant="primary" wire:click="rate('good')">{{ __('Good') }}</flux:button>
                <flux:button variant="filled" wire:click="rate('easy')">{{ __('Easy') }}</flux:button>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-neutral-200 p-8 text-center dark:border-neutral-700">
            <flux:heading size="md">{{ __('All caught up!') }}</flux:heading>
            <flux:text class="mt-2 text-neutral-500">
                {{ __('No due cards right now. Come back later or switch to Learn mode.') }}
            </flux:text>
        </div>
    @endif

    <flux:modal wire:model.self="showSettings" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Study settings') }}</flux:heading>
                <flux:text class="mt-2 text-sm text-neutral-500">
                    {{ __('Tune how this deck behaves during review.') }}
                </flux:text>
            </div>
            <div class="grid gap-4">
                <flux:select wire:model.defer="settings.algorithm" :label="__('Algorithm')">
                    <option value="sm2">{{ __('SM-2') }}</option>
                    <option value="fsrs">{{ __('FSRS (experimental)') }}</option>
                </flux:select>
                <flux:select wire:model.defer="settings.direction" :label="__('Direction')">
                    <option value="front">{{ __('Front to back') }}</option>
                    <option value="back">{{ __('Back to front') }}</option>
                    <option value="random">{{ __('Random') }}</option>
                </flux:select>
                <div class="flex flex-wrap gap-4">
                    <flux:switch wire:model.defer="settings.auto_play_audio" :label="__('Auto-play audio')" />
                    <flux:switch wire:model.defer="settings.muted" :label="__('Mute audio')" />
                </div>
                <flux:input wire:model.defer="settings.max_reviews_per_session" type="number" min="1" max="200" :label="__('Max cards per session')" />
                
                <div class="border-t border-neutral-200 pt-4 dark:border-neutral-700">
                    <flux:heading size="sm" class="mb-4">{{ __('Learning Steps') }}</flux:heading>
                    <flux:switch wire:model.defer="settings.learning_steps_enabled" :label="__('Enable learning steps')" />
                    
                    @if ($settings['learning_steps_enabled'] ?? false)
                        <div class="mt-4 space-y-4">
                            <flux:input 
                                wire:model.defer="settings.learning_steps_string" 
                                :label="__('Learning steps')" 
                                :placeholder="__('e.g., 1m, 10m, 1d')"
                                class="mt-2"
                            />
                            <flux:text class="text-xs text-neutral-500">
                                {{ __('Comma-separated intervals (e.g., 1m, 10m, 1d). Use m for minutes, h for hours, d for days.') }}
                            </flux:text>
                            
                            <flux:input 
                                wire:model.defer="settings.relearning_steps_string" 
                                :label="__('Relearning steps')" 
                                :placeholder="__('e.g., 10m')"
                                class="mt-2"
                            />
                            <flux:text class="text-xs text-neutral-500">
                                {{ __('Steps for cards that lapse during review.') }}
                            </flux:text>
                            
                            <flux:input 
                                wire:model.defer="settings.again_delay_cards" 
                                type="number" 
                                min="0" 
                                max="50" 
                                :label="__('Again delay (cards)')"
                                class="mt-2"
                            />
                            <flux:text class="text-xs text-neutral-500">
                                {{ __('Number of cards to show before requeuing "Again" (0 = immediate).') }}
                            </flux:text>
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="saveSettings">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showAiPanel" variant="floating" class="min-w-[22rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="md">{{ __('AI Study Assist') }}</flux:heading>
                <flux:text class="mt-2 text-sm text-neutral-500">
                    {{ __('Ask for explanations, mnemonics, or examples for this card.') }}
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
