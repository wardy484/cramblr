<div class="mx-auto flex min-w-0 w-full max-w-4xl flex-col gap-6" wire:poll.5s>
        <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('Processing job') }}</flux:heading>
                <flux:badge>{{ ucfirst($job->status->value) }}</flux:badge>
            </div>

            <div class="mt-4">
                @php
                    $percent = $job->progress_total > 0
                        ? (int) round(($job->progress_current / $job->progress_total) * 100)
                        : 0;
                    $pageCount = max(0, $job->progress_total - 1);
                    $isGeneratingCards = $job->status->value === 'processing'
                        && $job->progress_current === $pageCount
                        && $pageCount > 0;
                    $isComplete = $job->progress_total > 0 && $job->progress_current >= $job->progress_total;
                @endphp
                <div class="flex items-center justify-between text-sm text-neutral-500">
                    <span>{{ $job->progress_current }} / {{ $job->progress_total }}</span>
                    <span>
                        @if ($isComplete)
                            {{ __('Complete') }}
                        @elseif ($isGeneratingCards)
                            {{ __('Generating cards…') }}
                        @elseif ($pageCount > 0)
                            {{ __('Extracting pages') }}
                        @else
                            {{ __('Processing') }}
                        @endif
                    </span>
                </div>
                <div class="mt-2 h-2 w-full rounded-full bg-neutral-100 dark:bg-neutral-800">
                    <div class="h-2 rounded-full bg-blue-500" style="width: {{ $percent }}%"></div>
                </div>
            </div>

            @if ($job->status->value === 'needs_review')
                <flux:button class="mt-4" variant="primary" href="{{ route('jobs.review', ['job' => $job->id]) }}">
                    {{ __('Review cards') }}
                </flux:button>
            @endif

            @if ($job->status->value === 'failed')
                <div class="mt-4 flex flex-col gap-3">
                    @if ($job->error_message)
                        <flux:text class="text-sm text-red-600">{{ $job->error_message }}</flux:text>
                    @endif
                    <flux:button variant="primary" wire:click="retry" wire:loading.attr="disabled">
                        {{ __('Retry processing') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <flux:heading size="sm">{{ __('Extraction prompt') }}</flux:heading>
            <div class="mt-2 flex items-center gap-2">
                <flux:text class="text-sm text-neutral-500">
                    {{ __('Prompt for page') }} {{ $extractionPrompt['page_index'] }}
                </flux:text>
                <flux:badge>{{ ucfirst($job->translation_preference ?? 'phonetic') }} {{ __('translation') }}</flux:badge>
            </div>
            <div class="mt-4 grid gap-4">
                <div>
                    <flux:text class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                        {{ __('System') }}
                    </flux:text>
                    <div class="mt-2 max-w-full overflow-x-auto rounded-lg bg-neutral-100 p-3 dark:bg-neutral-800">
                        <pre class="whitespace-pre-wrap break-words text-xs text-neutral-700 dark:text-neutral-200" style="word-break: break-word; overflow-wrap: break-word;">{{ $extractionPrompt['system'] }}</pre>
                    </div>
                </div>
                <div>
                    <flux:text class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                        {{ __('User') }}
                    </flux:text>
                    <div class="mt-2 max-w-full overflow-x-auto rounded-lg bg-neutral-100 p-3 dark:bg-neutral-800">
                        <pre class="whitespace-pre-wrap break-words text-xs text-neutral-700 dark:text-neutral-200" style="word-break: break-word; overflow-wrap: break-word;">{{ $extractionPrompt['user'] }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
            <flux:heading size="sm">{{ __('Pages') }}</flux:heading>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($job->pages as $page)
                    @php
                        $imageUrl = URL::temporarySignedRoute(
                            'job-pages.image',
                            now()->addMinutes(10),
                            ['job' => $job->id, 'page' => $page->id]
                        );
                    @endphp
                    <div class="flex items-center gap-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                        <img src="{{ $imageUrl }}" alt="Page {{ $page->page_index }}" class="h-16 w-16 rounded object-cover" />
                        <div class="flex-1">
                            <flux:text>{{ __('Page') }} {{ $page->page_index }}</flux:text>
                            <flux:text class="text-sm text-neutral-500">{{ ucfirst($page->status->value) }}</flux:text>
                            @if ($page->error_message)
                                <flux:text class="text-sm text-red-600">{{ $page->error_message }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
