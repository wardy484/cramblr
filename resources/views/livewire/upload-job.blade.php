<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ __('Upload study images') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Add photos one at a time from your device or camera.') }}</flux:text>

            <form wire:submit="submit" class="mt-6 space-y-6">
                <flux:file-upload wire:model="newImage" accept="image/*" capture="environment">
                    <flux:file-upload.dropzone
                        heading="{{ __('Drop or click to add a photo') }}"
                        text="{{ __('JPG, PNG, GIF up to 10MB. Up to 20 photos.') }}"
                    />
                </flux:file-upload>

                @error('images')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror
                @error('newImage')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror
                @error('images.*')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror

                @if (count($images) > 0)
                    <div class="mt-6 flex flex-col gap-2">
                        @foreach ($images as $index => $image)
                            <flux:file-item
                                :heading="$image->getClientOriginalName()"
                                :image="$image->temporaryUrl()"
                                :size="$image->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove
                                        wire:click="removeImage({{ $index }})"
                                        :aria-label="__('Remove file: :name', ['name' => $image->getClientOriginalName()])"
                                    />
                                </x-slot>
                            </flux:file-item>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 space-y-6">
                    <flux:textarea
                        wire:model.defer="refinementPrompt"
                        :label="__('Refinement prompt (optional)')"
                        rows="4"
                    />

                    <flux:radio.group
                        wire:model="translationPreference"
                        :label="__('Translation preference')"
                        variant="segmented"
                    >
                        <flux:radio value="phonetic">{{ __('Phonetic') }}</flux:radio>
                        <flux:radio value="thai">{{ __('Thai') }}</flux:radio>
                    </flux:radio.group>

                    <flux:switch
                        wire:model="generateAudio"
                        :label="__('Generate audio')"
                        :description="__('Generate audio pronunciation for Thai text (saves tokens when disabled)')"
                    />
                </div>

                <flux:accordion>
                    <flux:accordion.item>
                        <flux:accordion.heading>
                            {{ __('View extraction prompt') }}
                        </flux:accordion.heading>
                        <flux:accordion.content>
                            <div class="space-y-3">
                                <div>
                                    <flux:text class="text-sm font-medium">{{ __('User prompt:') }}</flux:text>
                                    <flux:text class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                        Extract study content from this page.@if($refinementPrompt) {{ $refinementPrompt }}@endif
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium">{{ __('System instructions:') }}</flux:text>
                                    <flux:text class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                        You are a precise extraction engine. Return JSON only. Never hallucinate. If uncertain set fields to null and lower confidence.
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium">{{ __('Translation preference:') }}</flux:text>
                                    <flux:text class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                        @if($translationPreference === 'thai')
                                            For the translation field, provide the Thai script translation.
                                        @else
                                            For the translation field, provide phonetic transcription (romanization).
                                        @endif
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium">{{ __('Expected schema:') }}</flux:text>
                                    <pre class="mt-1 overflow-x-auto rounded bg-neutral-100 p-2 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"><code>{
  "language_guess": "string",
  "page_type": "vocab_list|dialogue|grammar|mixed|unknown",
  "items": [{
    "type": "vocab|phrase|sentence|grammar_point",
    "source_text": "string",
    "translation": "string|null",
    "pronunciation": "string|null",
    "notes": "string|null",
    "page_index": "number",
    "confidence": "number"
  }]
}</code></pre>
                                </div>
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">{{ __('Start processing') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Uploading...') }}</span>
                </flux:button>
            </form>
        </div>
    </div>
</div>
