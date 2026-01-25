<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CardAudioController extends Controller
{
    public function __invoke(Request $request, Card $card): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        abort_unless($card->user_id === Auth::id(), 403);
        abort_unless($card->audio_path !== null, 404);

        return Storage::disk('private')->response($card->audio_path, null, [
            'Content-Type' => 'audio/mpeg',
        ]);
    }
}
