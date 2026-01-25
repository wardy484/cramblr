<?php

use App\Services\OpenAI\OpenAIClient;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

test('retries chat create on transient curl errors', function () {
    $chat = \Mockery::mock();

    $chat->shouldReceive('create')
        ->once()
        ->andThrow(new \RuntimeException('cURL error 18: transfer closed with outstanding read data remaining'))
        ->once()
        ->andThrow(new \RuntimeException('Connection timed out'))
        ->once()
        ->andReturn(CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'ok',
                        'function_call' => null,
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

    OpenAI::shouldReceive('chat')
        ->once()
        ->andReturn($chat);

    $client = new OpenAIClient();

    $response = $client->request([
        'model' => 'gpt-5.2',
        'messages' => [
            [
                'role' => 'user',
                'content' => 'hello',
            ],
        ],
    ]);

    expect($response->choices[0]->message->content)->toBe('ok');
});

