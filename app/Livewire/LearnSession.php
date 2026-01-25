<?php

namespace App\Livewire;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardReview;
use App\Models\Deck;
use App\Services\OpenAI\OpenAIClient;
use App\Services\Study\Scheduler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LearnSession extends Component
{
    public string $deckId;

    public string $deckName;

    /**
     * @var array<string, mixed>
     */
    public array $settings = [];

    /**
     * @var array<int, array{card_id: string, reversed: bool}>
     */
    public array $queue = [];

    public ?string $currentCardId = null;

    public bool $showBack = false;

    public bool $showAiPanel = false;

    public string $aiMode = 'explain';

    /**
     * @var array<string, string>
     */
    public array $aiResponses = [];

    public function mount(Deck $deck): void
    {
        abort_unless($deck->user_id === Auth::id(), 403);

        $this->deckId = $deck->id;
        $this->deckName = $deck->name;
        $this->settings = $this->mergeSettings($deck->study_settings ?? []);
        $this->buildQueue();
    }

    public function render(): View
    {
        return view('livewire.learn-session')
            ->layout('layouts.app', ['title' => __('Learn :deck', ['deck' => $this->deckName])]);
    }

    public function flip(): void
    {
        $this->showBack = ! $this->showBack;
    }

    public function markLearned(Scheduler $scheduler): void
    {
        $card = $this->currentCard();

        if (! $card) {
            return;
        }

        $result = $scheduler->scheduleReview($card, 'good', $this->settings);

        $updates = [
            'study_state' => $result['study_state'],
            'due_at' => $result['due_at'],
            'interval' => $result['interval'],
            'ease' => $result['ease'],
            'repetitions' => $result['repetitions'],
            'lapses' => $result['lapses'],
            'last_reviewed_at' => $result['reviewed_at'],
            'learning_step_index' => $result['learning_step_index'] ?? null,
            'is_learning' => $result['is_learning'] ?? false,
            'is_relearning' => $result['is_relearning'] ?? false,
        ];

        if ($card->status === CardStatus::Proposed) {
            $updates['status'] = CardStatus::Approved;
            $updates['deck_id'] = $this->deckId;
        }

        $card->update($updates);

        CardReview::query()->create([
            'user_id' => Auth::id(),
            'card_id' => $card->id,
            'rating' => 'good',
            'interval' => $result['interval'],
            'ease' => $result['ease'],
            'reviewed_at' => $result['reviewed_at'],
            'algorithm' => $result['algorithm'],
            'due_at' => $result['due_at'],
            'data' => $result['data'],
        ]);

        $this->advanceQueue();
    }

    public function requestAssist(string $mode): void
    {
        $card = $this->currentCard();

        if (! $card) {
            return;
        }

        $key = $this->assistKey($card->id, $mode);
        $this->aiMode = $mode;
        $this->showAiPanel = true;

        if (array_key_exists($key, $this->aiResponses)) {
            return;
        }

        $cached = data_get($card->extra, "study_assist.{$mode}");
        if (is_string($cached) && $cached !== '') {
            $this->aiResponses[$key] = $cached;

            return;
        }

        $client = app(OpenAIClient::class);
        $payload = $client->studyAssistPayload($card->front, $card->back, $mode);
        $response = $client->request($payload);
        $content = trim($client->responseContent($response));

        $this->aiResponses[$key] = $content !== '' ? $content : __('No response yet.');
    }

    #[Computed]
    public function currentCard(): ?Card
    {
        if (! $this->currentCardId) {
            return null;
        }

        return Card::query()
            ->where('user_id', Auth::id())
            ->where('id', $this->currentCardId)
            ->whereIn('status', [CardStatus::Approved, CardStatus::Proposed])
            ->first();
    }

    /**
     * @return array{card_id: string, reversed: bool}|null
     */
    #[Computed]
    public function currentQueueItem(): ?array
    {
        if (! $this->currentCardId) {
            return null;
        }

        foreach ($this->queue as $item) {
            if ($item['card_id'] === $this->currentCardId) {
                return $item;
            }
        }

        return null;
    }

    #[Computed]
    public function frontText(): ?string
    {
        $card = $this->currentCard();
        $item = $this->currentQueueItem();

        if (! $card || ! $item) {
            return null;
        }

        return $item['reversed'] ? $card->back : $card->front;
    }

    #[Computed]
    public function backText(): ?string
    {
        $card = $this->currentCard();
        $item = $this->currentQueueItem();

        if (! $card || ! $item) {
            return null;
        }

        return $item['reversed'] ? $card->front : $card->back;
    }

    #[Computed]
    public function audioUrl(): ?string
    {
        $card = $this->currentCard();

        if (! $card || ! $card->audio_path) {
            return null;
        }

        return URL::temporarySignedRoute('cards.audio', now()->addMinutes(10), ['card' => $card->id]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function mergeSettings(array $settings): array
    {
        return array_merge($this->settingsDefaults(), $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsDefaults(): array
    {
        return [
            'algorithm' => 'sm2',
            'direction' => 'front',
            'auto_play_audio' => false,
            'muted' => false,
            'max_new_per_session' => 20,
        ];
    }

    private function buildQueue(): void
    {
        $max = (int) ($this->settings['max_new_per_session'] ?? 20);
        $direction = (string) ($this->settings['direction'] ?? 'front');

        $cards = Card::query()
            ->where('user_id', Auth::id())
            ->where('deck_id', $this->deckId)
            ->whereIn('status', [CardStatus::Approved, CardStatus::Proposed])
            ->where(function ($builder) {
                $builder->whereNull('study_state')
                    ->orWhere('study_state', 'new');
            })
            ->orderBy('created_at')
            ->limit($max)
            ->get();

        $this->queue = $cards->map(function (Card $card) use ($direction): array {
            return [
                'card_id' => $card->id,
                'reversed' => $this->resolveReversed($direction),
            ];
        })->all();

        $this->currentCardId = $this->queue[0]['card_id'] ?? null;
        $this->showBack = false;
        $this->showAiPanel = false;
    }

    private function advanceQueue(): void
    {
        array_shift($this->queue);
        $next = $this->queue[0]['card_id'] ?? null;

        $this->currentCardId = $next;
        $this->showBack = false;
        $this->showAiPanel = false;
    }

    private function resolveReversed(string $direction): bool
    {
        return match ($direction) {
            'back' => true,
            'random' => (bool) random_int(0, 1),
            default => false,
        };
    }

    private function assistKey(string $cardId, string $mode): string
    {
        return $cardId.'-'.$mode;
    }
}
