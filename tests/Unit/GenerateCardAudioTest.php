<?php

use App\Jobs\GenerateCardAudio;
use App\Models\Card;
use App\Models\User;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('skips generating audio when thai_text contains only romanization', function () {
    $user = User::factory()->create();
    $card = Card::factory()->for($user)->create([
        'audio_path' => null,
        'extra' => ['thai_text' => 'sawatdi krap'],
    ]);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('generateSpeech')->never();

    (new GenerateCardAudio($card->id))->handle($client);

    expect($card->fresh()->audio_path)->toBeNull();
});

test('generates audio when thai_text contains Thai script', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $card = Card::factory()->for($user)->create([
        'audio_path' => null,
        'extra' => ['thai_text' => 'สวัสดี'],
    ]);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('generateSpeech')
        ->once()
        ->with('สวัสดี', 'alloy')
        ->andReturn('fake-mp3-bytes');

    (new GenerateCardAudio($card->id))->handle($client);

    $card->refresh();
    expect($card->audio_path)->not->toBeNull();
    expect($card->audio_path)->toContain('cards/');
    expect($card->audio_path)->toEndWith('.mp3');
});
