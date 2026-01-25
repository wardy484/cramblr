<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\Deck\DeckSuggestionService;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use function Pest\Laravel\mock;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('balances deck suggestions when a deck is oversized', function () {
    $user = User::factory()->create();
    $largeDeck = Deck::factory()->for($user)->create(['name' => 'Vocab']);
    $smallDeck = Deck::factory()->for($user)->create(['name' => 'Phrases']);

    Card::factory()->for($user)->for($largeDeck)->count(100)->create();
    Card::factory()->for($user)->for($smallDeck)->count(1)->create();

    $cards = [
        ['front' => 'sawatdee', 'back' => 'hello', 'tags' => ['greetings']],
        ['front' => 'khop khun', 'back' => 'thanks', 'tags' => ['greetings']],
        ['front' => 'chai', 'back' => 'yes', 'tags' => ['responses']],
        ['front' => 'mai chai', 'back' => 'no', 'tags' => ['responses']],
    ];

    $responseJson = json_encode([
        'suggestions' => [
            ['card_index' => 0, 'deck_id' => $largeDeck->id, 'reason' => 'Greeting'],
            ['card_index' => 1, 'deck_id' => $largeDeck->id, 'reason' => 'Greeting'],
            ['card_index' => 2, 'deck_id' => $largeDeck->id, 'reason' => 'Response'],
            ['card_index' => 3, 'deck_id' => $largeDeck->id, 'reason' => 'Response'],
        ],
    ], JSON_THROW_ON_ERROR);

    $response = makeChatResponse($responseJson);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('simpleTextPayload')->andReturn([]);
    $client->shouldReceive('request')->andReturn($response);
    $client->shouldReceive('responseContent')->andReturn($responseJson);

    $service = new DeckSuggestionService($client);
    $suggestions = $service->suggestDecks($user->id, $cards);

    expect(array_unique($suggestions))->toBe([(string) $smallDeck->id]);
});

test('creates a new deck when suggested', function () {
    $user = User::factory()->create();
    $existingDeck = Deck::factory()->for($user)->create(['name' => 'Basics']);

    $cards = [
        ['front' => 'khao pad', 'back' => 'fried rice', 'tags' => ['food']],
    ];

    $responseJson = json_encode([
        'suggestions' => [
            ['card_index' => 0, 'new_deck_name' => 'Food & Dining', 'reason' => 'Topic'],
        ],
    ], JSON_THROW_ON_ERROR);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('simpleTextPayload')->andReturn([]);
    $client->shouldReceive('request')->andReturn(makeChatResponse($responseJson));
    $client->shouldReceive('responseContent')->andReturn($responseJson);

    $service = new DeckSuggestionService($client);
    $suggestions = $service->suggestDecks($user->id, $cards);

    $createdDeck = Deck::query()
        ->where('user_id', $user->id)
        ->where('name', 'Food & Dining')
        ->first();

    expect($createdDeck)->not->toBeNull();
    expect($suggestions[0])->toBe((string) $createdDeck->id);
    expect($existingDeck->id)->not->toBe($createdDeck->id);
});

test('ignores generic deck suggestions when specific decks exist', function () {
    $user = User::factory()->create();
    $inbox = Deck::factory()->for($user)->create(['name' => 'Inbox']);
    $locations = Deck::factory()->for($user)->create(['name' => 'Locations']);

    $cards = [
        ['front' => 'thi nai', 'back' => 'where', 'tags' => ['location']],
    ];

    $responseJson = json_encode([
        'suggestions' => [
            ['card_index' => 0, 'deck_id' => $inbox->id, 'reason' => 'Default'],
        ],
    ], JSON_THROW_ON_ERROR);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('simpleTextPayload')->andReturn([]);
    $client->shouldReceive('request')->andReturn(makeChatResponse($responseJson));
    $client->shouldReceive('responseContent')->andReturn($responseJson);

    $service = new DeckSuggestionService($client);
    $suggestions = $service->suggestDecks($user->id, $cards);

    expect($suggestions[0])->toBe((string) $locations->id);
});

test('creates a deck from tags when only generic decks exist', function () {
    $user = User::factory()->create();
    $inbox = Deck::factory()->for($user)->create(['name' => 'Inbox']);

    $cards = [
        ['front' => 'thi nai', 'back' => 'where', 'tags' => ['locations']],
    ];

    $responseJson = json_encode([
        'suggestions' => [
            ['card_index' => 0, 'deck_id' => $inbox->id, 'reason' => 'Default'],
        ],
    ], JSON_THROW_ON_ERROR);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('simpleTextPayload')->andReturn([]);
    $client->shouldReceive('request')->andReturn(makeChatResponse($responseJson));
    $client->shouldReceive('responseContent')->andReturn($responseJson);

    $service = new DeckSuggestionService($client);
    $suggestions = $service->suggestDecks($user->id, $cards);

    $createdDeck = Deck::query()
        ->where('user_id', $user->id)
        ->where('name', 'Locations')
        ->first();

    expect($createdDeck)->not->toBeNull();
    expect($suggestions[0])->toBe((string) $createdDeck->id);
});

test('includes sample cards in deck context prompt', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create(['name' => 'Locations']);

    Card::factory()->for($user)->for($deck)->create([
        'front' => 'thi nai',
        'back' => 'where',
        'tags' => ['location'],
    ]);

    $matched = false;

    $responseJson = json_encode([
        'suggestions' => [
            ['card_index' => 0, 'deck_id' => $deck->id, 'reason' => 'Matches'],
        ],
    ], JSON_THROW_ON_ERROR);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('simpleTextPayload')
        ->with(\Mockery::on(function (string $prompt) use (&$matched): bool {
            $matched = str_contains($prompt, 'Locations')
                && str_contains($prompt, 'thi nai')
                && str_contains($prompt, 'where');

            return $matched;
        }))
        ->andReturn([]);
    $client->shouldReceive('request')->andReturn(makeChatResponse($responseJson));
    $client->shouldReceive('responseContent')->andReturn($responseJson);

    $service = new DeckSuggestionService($client);
    $service->suggestDecks($user->id, [
        ['front' => 'tee nai', 'back' => 'where is', 'tags' => ['location']],
    ]);

    expect($matched)->toBeTrue();
});

test('groups cards from the same page into one deck', function () {
    $user = User::factory()->create();
    $comparative = Deck::factory()->for($user)->create(['name' => 'Comparative Sentences']);
    $locations = Deck::factory()->for($user)->create(['name' => 'Locations']);

    $cards = [
        ['front' => 'thi nai', 'back' => 'where', 'tags' => ['locations'], 'extra' => ['page_index' => 1]],
        ['front' => 'more than', 'back' => 'comparison', 'tags' => ['comparative'], 'extra' => ['page_index' => 1]],
    ];

    $responseJson = json_encode([
        'suggestions' => [
            ['card_index' => 0, 'deck_id' => $locations->id, 'reason' => 'Location'],
            ['card_index' => 1, 'deck_id' => $comparative->id, 'reason' => 'Comparative'],
        ],
    ], JSON_THROW_ON_ERROR);

    $client = mock(OpenAIClient::class);
    $client->shouldReceive('simpleTextPayload')->andReturn([]);
    $client->shouldReceive('request')->andReturn(makeChatResponse($responseJson));
    $client->shouldReceive('responseContent')->andReturn($responseJson);

    $service = new DeckSuggestionService($client);
    $suggestions = $service->suggestDecks($user->id, $cards);

    expect($suggestions[0])->toBe($suggestions[1]);
});

function makeChatResponse(string $content): CreateResponse
{
    return CreateResponse::from([
        'id' => 'response-id',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'gpt-4o',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $content,
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 1,
            'completion_tokens' => 1,
            'total_tokens' => 2,
        ],
    ], MetaInformation::from([]));
}
