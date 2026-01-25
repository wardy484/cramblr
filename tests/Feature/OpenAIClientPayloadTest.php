<?php

use App\Services\OpenAI\OpenAIClient;

test('builds extraction prompt that respects translation preference', function () {
    config()->set('openai.vision_model', 'gpt-4o');

    $client = new OpenAIClient();
    $payload = $client->extractionPayload('base64', null, 2, 'thai');
    $systemMessage = $payload['messages'][0]['content'] ?? '';

    expect($systemMessage)->toContain('Use only the text on the page; do not translate or invent.');
    expect($systemMessage)->toContain('If both Thai script and romanization are present');
    expect($systemMessage)->toContain('For the translation field, provide the Thai script.');
    expect($systemMessage)->toContain('If romanization appears, put it in pronunciation.');
});
