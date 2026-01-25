<?php

namespace App\Console\Commands;

use App\Services\OpenAI\OpenAIClient;
use Illuminate\Console\Command;

class TestOpenAI extends Command
{
    protected $signature = 'openai:test {prompt?}';

    protected $description = 'Test OpenAI API with a simple text prompt';

    public function handle(OpenAIClient $client): int
    {
        $prompt = $this->argument('prompt') ?? 'Say hello in one sentence.';

        $this->info("Testing OpenAI API with prompt: {$prompt}");
        $this->newLine();

        try {
            $payload = $client->simpleTextPayload($prompt);
            $this->info('Sending request...');
            
            $response = $client->request($payload);
            $content = $client->responseContent($response);
            
            $this->newLine();
            $this->info('✅ Success! Response:');
            $this->line($content);
            
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('❌ Failed!');
            $this->error('Error: '.$exception->getMessage());
            $this->error('Class: '.get_class($exception));
            
            return self::FAILURE;
        }
    }
}
