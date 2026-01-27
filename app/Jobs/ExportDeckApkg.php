<?php

namespace App\Jobs;

use App\Enums\CardStatus;
use App\Enums\ExportStatus;
use App\Models\Deck;
use App\Models\Export;
use App\Services\Anki\AnkiPackageBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExportDeckApkg implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public string $exportId) {}

    /**
     * Execute the job.
     */
    public function handle(AnkiPackageBuilder $builder): void
    {
        $export = Export::query()
            ->with('deck')
            ->findOrFail($this->exportId);

        $export->update([
            'status' => ExportStatus::Building,
            'error_message' => null,
        ]);

        try {
            $deckIds = $this->deckIdsWithChildren($export->deck);

            $cards = $export->deck
                ->cards()
                ->whereIn('deck_id', $deckIds)
                ->where('status', CardStatus::Approved)
                ->get();

            if ($cards->isEmpty()) {
                $export->update([
                    'status' => ExportStatus::Failed,
                    'error_message' => 'No approved cards available for export.',
                ]);

                return;
            }

            $localPath = storage_path('app/private/exports/'.Str::uuid().'.apkg');
            $builder->build($export->deck, $cards, $localPath);

            $remoteDirectory = 'exports/'.$export->user_id;
            $remoteFileName = $export->id.'.apkg';
            $remotePath = $remoteDirectory.'/'.$remoteFileName;

            Storage::disk('private')->putFileAs($remoteDirectory, new File($localPath), $remoteFileName);

            $export->update([
                'status' => ExportStatus::Ready,
                'apkg_path' => $remotePath,
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => ExportStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        } finally {
            if (isset($localPath) && file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function deckIdsWithChildren(Deck $deck): array
    {
        $ids = [$deck->id];

        foreach ($deck->children as $child) {
            $ids = array_merge($ids, $this->deckIdsWithChildren($child));
        }

        return $ids;
    }
}
