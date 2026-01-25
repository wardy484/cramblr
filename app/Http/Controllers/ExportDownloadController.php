<?php

namespace App\Http\Controllers;

use App\Enums\ExportStatus;
use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, Export $export): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        abort_unless($export->user_id === Auth::id(), 403);

        if ($export->status !== ExportStatus::Ready || $export->apkg_path === null) {
            abort(404);
        }

        $filename = Str::slug($export->deck?->name ?? 'deck').'.apkg';

        return Storage::disk('private')->download($export->apkg_path, $filename);
    }
}
