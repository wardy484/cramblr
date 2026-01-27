<?php

namespace App\Http\Controllers;

use App\Models\ExtractionJob;
use App\Models\JobPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobPageImageController extends Controller
{
    public function __invoke(Request $request, ExtractionJob $job, JobPage $page): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        abort_unless($job->user_id === Auth::id(), 403);
        abort_unless($page->job_id === $job->id, 404);

        $disk = Storage::disk('private');
        $path = $page->image_path;

        $response = $disk->response($path);

        $mimeType = $disk->mimeType($path);
        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        $response->headers->set('Cache-Control', 'private, max-age=600');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }
}
