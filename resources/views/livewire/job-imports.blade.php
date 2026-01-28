<div class="mx-auto flex w-full max-w-4xl flex-col gap-6" wire:poll.10s>
    <div class="rounded-2xl border border-neutral-200 bg-gradient-to-br from-white via-white to-blue-50/50 p-5 dark:border-neutral-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-blue-900/15 sm:p-6">
        <div class="flex min-w-0 items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                <flux:icon name="folder-git-2" class="size-5" />
            </div>
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Imports') }}</flux:heading>
                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Create cards from images or manage your import queue.') }}
                </flux:text>
            </div>
        </div>

        <div class="mt-4">
            <flux:select wire:model="status" :label="__('Status')">
                @foreach ($this->statusFilters() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="mt-4 flex w-full gap-2 sm:w-auto sm:flex-initial">
            <flux:button
                variant="outline"
                size="sm"
                icon="trash"
                class="min-w-0 flex-1 sm:flex-initial"
                wire:click="clearPendingImports"
                wire:confirm="{{ __('Clear all pending imports?') }}"
                :disabled="$this->pendingImportCount() === 0"
            >
                {{ __('Clear pending') }}
            </flux:button>
            <flux:button variant="primary" size="sm" icon="plus" class="min-w-0 flex-1 sm:flex-initial" href="{{ route('jobs.create') }}">
                {{ __('Create from images') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="sm">{{ __('Import queue') }}</flux:heading>
        <div class="mt-4">
            <div class="flex flex-col gap-3 lg:hidden">
                @forelse ($jobs as $job)
                    @php
                        $statusValue = $job->status->value;
                        $statusColor = match ($statusValue) {
                            'queued' => 'zinc',
                            'processing' => 'blue',
                            'needs_review' => 'amber',
                            'failed' => 'red',
                            'completed' => 'green',
                            default => 'zinc',
                        };
                        $progress = $job->progress_total > 0
                            ? (int) round(($job->progress_current / $job->progress_total) * 100)
                            : 0;
                        $actionRoute = $statusValue === 'needs_review'
                            ? route('jobs.review', ['job' => $job->id])
                            : route('jobs.progress', ['job' => $job->id]);
                        $actionLabel = $statusValue === 'needs_review'
                            ? __('Review cards')
                            : __('View progress');
                    @endphp

                    <flux:card size="sm" class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <flux:text class="font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                    {{ $job->id }}
                                </flux:text>
                                <flux:text class="mt-1 block text-xs text-neutral-500">
                                    {{ $job->created_at->toDayDateTimeString() }}
                                </flux:text>
                            </div>
                            <flux:badge size="sm" :color="$statusColor" inset="top bottom">
                                {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                            </flux:badge>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <flux:text class="text-xs text-neutral-500">
                                    {{ __('Progress') }}
                                </flux:text>
                                <flux:text class="text-xs text-neutral-500">
                                    {{ $job->progress_current }} / {{ $job->progress_total }}
                                    <span class="text-neutral-400 dark:text-neutral-500">·</span>
                                    {{ $progress }}%
                                </flux:text>
                            </div>
                            <div class="h-2 w-full rounded-full bg-neutral-100 dark:bg-neutral-800" aria-label="{{ __('Progress') }}">
                                <div class="h-2 rounded-full bg-sprout-500" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <flux:text class="text-xs text-neutral-500">
                                {{ __('Updated') }}: {{ $job->updated_at->diffForHumans() }}
                            </flux:text>
                            <div class="flex shrink-0 items-center gap-2">
                                <flux:button variant="outline" size="sm" href="{{ $actionRoute }}">
                                    {{ $actionLabel }}
                                </flux:button>
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    icon="trash"
                                    square
                                    icon:variant="outline"
                                    icon:class="size-4 shrink-0"
                                    class="flex items-center justify-center"
                                    aria-label="{{ __('Delete') }}"
                                    wire:click="deleteImport('{{ $job->id }}')"
                                    wire:confirm="{{ __('Delete this import?') }}"
                                ></flux:button>
                            </div>
                        </div>
                    </flux:card>
                @empty
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('No imports found.') }}
                    </flux:text>
                @endforelse

                <div>
                    {{ $jobs->links() }}
                </div>
            </div>

            <div class="hidden lg:block">
                <flux:table :paginate="$jobs">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Job') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Progress') }}</flux:table.column>
                        <flux:table.column>{{ __('Updated') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($jobs as $job)
                            @php
                                $statusValue = $job->status->value;
                                $statusColor = match ($statusValue) {
                                    'queued' => 'zinc',
                                    'processing' => 'blue',
                                    'needs_review' => 'amber',
                                    'failed' => 'red',
                                    'completed' => 'green',
                                    default => 'zinc',
                                };
                                $progress = $job->progress_total > 0
                                    ? (int) round(($job->progress_current / $job->progress_total) * 100)
                                    : 0;
                                $actionRoute = $statusValue === 'needs_review'
                                    ? route('jobs.review', ['job' => $job->id])
                                    : route('jobs.progress', ['job' => $job->id]);
                                $actionLabel = $statusValue === 'needs_review'
                                    ? __('Review cards')
                                    : __('View progress');
                            @endphp
                            <flux:table.row :key="$job->id">
                                <flux:table.cell>
                                    <div class="grid gap-1">
                                        <flux:text class="font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                            {{ $job->id }}
                                        </flux:text>
                                        <flux:text class="text-xs text-neutral-500">
                                            {{ $job->created_at->toDayDateTimeString() }}
                                        </flux:text>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$statusColor" inset="top bottom">
                                        {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-24 rounded-full bg-neutral-100 dark:bg-neutral-800">
                                            <div class="h-2 rounded-full bg-sprout-500" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <flux:text class="text-xs text-neutral-500">
                                            {{ $job->progress_current }} / {{ $job->progress_total }}
                                        </flux:text>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:text class="text-xs text-neutral-500">
                                        {{ $job->updated_at->diffForHumans() }}
                                    </flux:text>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" href="{{ $actionRoute }}">
                                            {{ $actionLabel }}
                                        </flux:button>
                                        <flux:button
                                            variant="outline"
                                            size="sm"
                                            icon="trash"
                                            square
                                            icon:variant="outline"
                                            icon:class="size-4 shrink-0"
                                            class="flex items-center justify-center"
                                            aria-label="{{ __('Delete') }}"
                                            wire:click="deleteImport('{{ $job->id }}')"
                                            wire:confirm="{{ __('Delete this import?') }}"
                                        ></flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <flux:text class="text-sm text-neutral-500">
                                        {{ __('No imports found.') }}
                                    </flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </div>
</div>
