<?php

namespace App\Livewire;

use App\Enums\ExtractionJobStatus;
use App\Models\ExtractionJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class JobImports extends Component
{
    use WithPagination;

    public string $status = 'active';

    public function render(): View
    {
        return view('livewire.job-imports', [
            'jobs' => $this->jobs(),
        ])->layout('layouts.app', ['title' => __('Imports')]);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statusFilters(): array
    {
        return [
            'active' => __('Active'),
            ExtractionJobStatus::Queued->value => __('Queued'),
            ExtractionJobStatus::Processing->value => __('Processing'),
            ExtractionJobStatus::NeedsReview->value => __('Needs review'),
            ExtractionJobStatus::Failed->value => __('Failed'),
            ExtractionJobStatus::Completed->value => __('Completed'),
            'all' => __('All'),
        ];
    }

    #[Computed]
    public function jobs(): LengthAwarePaginator
    {
        $query = ExtractionJob::query()
            ->where('user_id', Auth::id())
            ->latest();

        if ($this->status === 'active') {
            $query->whereIn('status', [
                ExtractionJobStatus::Queued,
                ExtractionJobStatus::Processing,
                ExtractionJobStatus::NeedsReview,
            ]);
        } elseif ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        return $query->paginate(15);
    }
}
