<?php

use App\Services\OpenAI\OpenAIClient;

test('builds extraction prompt that respects translation preference', function () {
    config()->set('openai.vision_model', 'gpt-4o');

    $client = new OpenAIClient;
    $payload = $client->extractionPayload('base64', null, 2, 'thai');
    $systemMessage = $payload['messages'][0]['content'] ?? '';

    expect($systemMessage)->toContain('Use only the text on the page; do not translate or invent.');
    expect($systemMessage)->toContain('If both Thai script and romanization are present');
    expect($systemMessage)->toContain('For the translation field, provide the Thai script.');
    expect($systemMessage)->toContain('If romanization appears, put it in pronunciation.');
});

test('card generation payload requires thai_text to be Thai script only for audio', function () {
    config()->set('openai.cards_model', 'gpt-4o-mini');

    $client = new OpenAIClient;
    $payload = $client->cardGenerationPayload([], null, 'phonetic');
    $content = $payload['messages'][0]['content'] ?? '';

    expect($content)->toContain('thai_text');
    expect($content)->toContain('native Thai script');
    expect($content)->toContain('audio pronunciation');
    expect($content)->toContain('never put romanization in thai_text');
});
