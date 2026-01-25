<div class="mx-auto flex w-full max-w-4xl flex-col gap-6" wire:poll.10s>
    <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Imports') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    variant="danger"
                    wire:click="clearPendingImports"
                    wire:confirm="{{ __('Clear all pending imports?') }}"
                    :disabled="$this->pendingImportCount() === 0"
                >
                    {{ __('Clear pending') }}
                </flux:button>
                <flux:button variant="primary" href="{{ route('jobs.create') }}">
                    {{ __('Create from images') }}
                </flux:button>
            </div>
        </div>

        <div class="mt-4">
            <flux:select wire:model="status" :label="__('Status')">
                @foreach ($this->statusFilters() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="sm">{{ __('Import queue') }}</flux:heading>
        <div class="mt-4">
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
                                        <div class="h-2 rounded-full bg-blue-500" style="width: {{ $progress }}%"></div>
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
                                <flux:button variant="ghost" size="sm" href="{{ $actionRoute }}">
                                    {{ $actionLabel }}
                                </flux:button>
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
