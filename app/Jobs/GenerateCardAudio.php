<?php

namespace App\Jobs;

use App\Models\Card;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateCardAudio implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public string $cardId) {}

    public function handle(OpenAIClient $client): void
    {
        $card = Card::query()->findOrFail($this->cardId);

        if ($card->audio_path !== null) {
            return;
        }

        $thaiText = data_get($card->extra, 'thai_text');

        if (empty($thaiText) || ! $this->containsThaiScript($thaiText)) {
            return;
        }

        try {
            $audioContent = $client->generateSpeech($thaiText, 'alloy');

            $path = 'cards/'.$card->id.'/'.Str::uuid()->toString().'.mp3';
            Storage::disk('private')->put($path, $audioContent);

            $card->update(['audio_path' => $path]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to generate audio for card', [
                'card_id' => $card->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function containsThaiScript(string $text): bool
    {
        return (bool) preg_match('/[\x{0E00}-\x{0E7F}]/u', $text);
    }
}
