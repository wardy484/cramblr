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
use Livewire\Attributes\Url as LivewireUrl;
use Livewire\Component;

class StudySession extends Component
{
    public string $deckId;

    public string $deckName;

    #[LivewireUrl]
    public bool $recap = false;

    /**
     * @var array<string, mixed>
     */
    public array $settings = [];

    /**
     * @var array<int, array{card_id: string, reversed: bool}>
     */
    public array $queue = [];

    /**
     * @var array<string, array<string, int>>
     */
    public array $sessionStats = [];

    public ?string $currentCardId = null;

    public bool $showBack = false;

    public bool $showAiPanel = false;

    public string $aiMode = 'explain';

    /**
     * @var array<string, string>
     */
    public array $aiResponses = [];

    public bool $showSettings = false;

    public function mount(Deck $deck, bool $recap = false): void
    {
        abort_unless($deck->user_id === Auth::id(), 403);

        $this->deckId = $deck->id;
        $this->deckName = $deck->name;
        $this->settings = $this->mergeSettings($deck->study_settings ?? []);
        $this->recap = $recap || $this->recap;
        $this->prepareSettingsForView();
        $this->buildQueue();
    }

    public function render(): View
    {
        return view('livewire.study-session')
            ->layout('layouts.app', ['title' => __('Study :deck', ['deck' => $this->deckName])]);
    }

    public function flip(): void
    {
        $this->showBack = ! $this->showBack;
    }

    public function toggleSettings(): void
    {
        $this->showSettings = ! $this->showSettings;
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'settings.algorithm' => ['required', 'in:sm2,fsrs'],
            'settings.direction' => ['required', 'in:front,back,random'],
            'settings.auto_play_audio' => ['required', 'boolean'],
            'settings.muted' => ['required', 'boolean'],
            'settings.max_reviews_per_session' => ['required', 'integer', 'min:1', 'max:200'],
            'settings.learning_steps_enabled' => ['sometimes', 'boolean'],
            'settings.learning_steps_string' => ['sometimes', 'nullable', 'string'],
            'settings.relearning_steps_string' => ['sometimes', 'nullable', 'string'],
            'settings.again_delay_cards' => ['sometimes', 'integer', 'min:0', 'max:50'],
        ]);

        if (isset($validated['settings']['learning_steps_string'])) {
            $validated['settings']['learning_steps'] = $this->parseStepsString($validated['settings']['learning_steps_string']);
            unset($validated['settings']['learning_steps_string']);
        }

        if (isset($validated['settings']['relearning_steps_string'])) {
            $validated['settings']['relearning_steps'] = $this->parseStepsString($validated['settings']['relearning_steps_string']);
            unset($validated['settings']['relearning_steps_string']);
        }

        Deck::query()
            ->where('id', $this->deckId)
            ->where('user_id', Auth::id())
            ->update(['study_settings' => $validated['settings']]);

        $this->settings = $this->mergeSettings($validated['settings']);
        $this->prepareSettingsForView();
        $this->buildQueue();
    }

    private function prepareSettingsForView(): void
    {
        if (isset($this->settings['learning_steps']) && is_array($this->settings['learning_steps'])) {
            $this->settings['learning_steps_string'] = implode(', ', $this->settings['learning_steps']);
        } else {
            $this->settings['learning_steps_string'] = '1m, 10m, 1d';
        }

        if (isset($this->settings['relearning_steps']) && is_array($this->settings['relearning_steps'])) {
            $this->settings['relearning_steps_string'] = implode(', ', $this->settings['relearning_steps']);
        } else {
            $this->settings['relearning_steps_string'] = '10m';
        }
    }

    /**
     * @return array<string>
     */
    private function parseStepsString(?string $stepsString): array
    {
        if (! $stepsString) {
            return [];
        }

        $steps = array_map('trim', explode(',', $stepsString));
        $steps = array_filter($steps, function ($step) {
            return preg_match('/^\d+[mhd]$/i', $step);
        });

        return array_values($steps);
    }

    public function rate(string $rating, Scheduler $scheduler): void
    {
        $card = $this->currentCard();
        $queueItem = $this->currentQueueItem();

        if (! $card || ! $queueItem) {
            return;
        }

        $cardId = $card->id;
        $result = $scheduler->scheduleReview($card, $rating, $this->settings);

        $card->update([
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
        ]);

        CardReview::query()->create([
            'user_id' => Auth::id(),
            'card_id' => $card->id,
            'rating' => $rating,
            'interval' => $result['interval'],
            'ease' => $result['ease'],
            'reviewed_at' => $result['reviewed_at'],
            'algorithm' => $result['algorithm'] ?? 'sm2',
            'due_at' => $result['due_at'],
            'data' => $result['data'] ?? [],
        ]);

        $shouldRequeue = $this->shouldRequeueCard($card, $rating, $result);

        $this->advanceQueue();

        if ($shouldRequeue) {
            $this->bumpSessionStat($cardId, $rating);
            $this->requeueCard($queueItem, $card, $rating, $result);
        }
    }

    public function extendSession(): void
    {
        $this->buildQueue(true);
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
            ->where('status', CardStatus::Approved)
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

    #[Computed]
    public function currentStepInfo(): ?array
    {
        $card = $this->currentCard();
        $learningStepsEnabled = (bool) ($this->settings['learning_steps_enabled'] ?? false);

        if (! $card || ! $learningStepsEnabled) {
            return null;
        }

        $isLearning = (bool) ($card->is_learning ?? false);
        $isRelearning = (bool) ($card->is_relearning ?? false);

        if (! $isLearning && ! $isRelearning) {
            return null;
        }

        $steps = $isRelearning
            ? (array) ($this->settings['relearning_steps'] ?? ['10m'])
            : (array) ($this->settings['learning_steps'] ?? ['1m', '10m', '1d']);

        $currentStepIndex = (int) ($card->learning_step_index ?? 0);
        $totalSteps = count($steps);

        return [
            'current' => $currentStepIndex + 1,
            'total' => $totalSteps,
            'is_learning' => $isLearning,
            'is_relearning' => $isRelearning,
        ];
    }

    #[Computed]
    public function predictedNextInterval(): ?string
    {
        $card = $this->currentCard();
        $learningStepsEnabled = (bool) ($this->settings['learning_steps_enabled'] ?? false);

        if (! $card || ! $learningStepsEnabled) {
            return null;
        }

        $isLearning = (bool) ($card->is_learning ?? false);
        $isRelearning = (bool) ($card->is_relearning ?? false);

        if (! $isLearning && ! $isRelearning) {
            return null;
        }

        $steps = $isRelearning
            ? (array) ($this->settings['relearning_steps'] ?? ['10m'])
            : (array) ($this->settings['learning_steps'] ?? ['1m', '10m', '1d']);

        $currentStepIndex = (int) ($card->learning_step_index ?? 0);
        $nextStepIndex = $currentStepIndex + 1;

        if ($nextStepIndex >= count($steps)) {
            return __('Graduating to review');
        }

        $nextStep = $steps[$nextStepIndex] ?? null;
        if (! $nextStep) {
            return null;
        }

        return $this->formatStepInterval($nextStep);
    }

    private function formatStepInterval(string $step): string
    {
        $step = strtolower(trim($step));

        if (preg_match('/^(\d+)m$/', $step, $matches)) {
            $minutes = (int) $matches[1];
            return $minutes === 1 ? __('1 minute') : __(':count minutes', ['count' => $minutes]);
        }

        if (preg_match('/^(\d+)h$/', $step, $matches)) {
            $hours = (int) $matches[1];
            return $hours === 1 ? __('1 hour') : __(':count hours', ['count' => $hours]);
        }

        if (preg_match('/^(\d+)d$/', $step, $matches)) {
            $days = (int) $matches[1];
            return $days === 1 ? __('1 day') : __(':count days', ['count' => $days]);
        }

        return $step;
    }

    /**
     * @param array<string, mixed> $settings
     *
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
            'max_reviews_per_session' => 50,
            'learning_steps_enabled' => false,
            'learning_steps' => ['1m', '10m', '1d'],
            'relearning_steps' => ['10m'],
            'again_delay_cards' => 0,
        ];
    }

    private function buildQueue(bool $append = false): void
    {
        $max = (int) ($this->settings['max_reviews_per_session'] ?? 50);
        $direction = (string) ($this->settings['direction'] ?? 'front');
        $excludeIds = $append ? $this->queuedCardIds() : [];

        $cards = $this->recap
            ? $this->recentCards($max, $excludeIds)
            : $this->dueCards($max, $excludeIds);

        $newItems = $cards->map(function (Card $card) use ($direction): array {
            return [
                'card_id' => $card->id,
                'reversed' => $this->resolveReversed($direction),
            ];
        })->all();

        if ($append) {
            $this->queue = array_merge($this->queue, $newItems);
        } else {
            $this->queue = $newItems;
            $this->currentCardId = $this->queue[0]['card_id'] ?? null;
            $this->showBack = false;
            $this->showAiPanel = false;
        }
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

    /**
     * @param array<string, mixed> $result
     */
    private function shouldRequeueCard(Card $card, string $rating, array $result): bool
    {
        $learningStepsEnabled = (bool) ($this->settings['learning_steps_enabled'] ?? false);

        if (! $learningStepsEnabled) {
            return in_array($rating, ['again', 'hard', 'good'], true);
        }

        if ($rating === 'easy') {
            return false;
        }

        $isLearning = (bool) ($result['is_learning'] ?? false);
        $isRelearning = (bool) ($result['is_relearning'] ?? false);
        $studyState = (string) ($result['study_state'] ?? 'review');

        if ($rating === 'again') {
            $againDelayCards = (int) ($this->settings['again_delay_cards'] ?? 0);
            if ($againDelayCards === 0) {
                return true;
            }
            return $againDelayCards > 0;
        }

        if ($isLearning || $isRelearning) {
            return $studyState === 'learning' || $studyState === 'relearning';
        }

        return in_array($rating, ['again', 'hard', 'good'], true);
    }

    /**
     * @param array{card_id: string, reversed: bool} $queueItem
     * @param array<string, mixed> $result
     */
    private function requeueCard(array $queueItem, Card $card, string $rating, array $result): void
    {
        $position = $this->requeuePosition($card, $rating, $result);

        if ($position === null) {
            return;
        }

        array_splice($this->queue, $position, 0, [$queueItem]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function requeuePosition(Card $card, string $rating, array $result): ?int
    {
        $learningStepsEnabled = (bool) ($this->settings['learning_steps_enabled'] ?? false);

        if ($learningStepsEnabled && $rating === 'again') {
            $againDelayCards = (int) ($this->settings['again_delay_cards'] ?? 0);
            if ($againDelayCards > 0) {
                return min($againDelayCards, count($this->queue));
            }
            return 0;
        }

        $spacing = $this->spacingForRating($card, $rating, $result);

        return min($spacing, count($this->queue));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function spacingForRating(Card $card, string $rating, array $result): int
    {
        $learningStepsEnabled = (bool) ($this->settings['learning_steps_enabled'] ?? false);

        if ($learningStepsEnabled) {
            $isLearning = (bool) ($result['is_learning'] ?? false);
            $isRelearning = (bool) ($result['is_relearning'] ?? false);

            if ($isLearning || $isRelearning) {
                $intervalMinutes = (int) data_get($result, 'data.interval_minutes', 0);
                if ($intervalMinutes > 0 && $intervalMinutes < 60) {
                    return match ($rating) {
                        'again' => 1,
                        'hard' => 2,
                        'good' => 4,
                        default => 7,
                    };
                }
            }
        }

        $base = match ($rating) {
            'again' => 1,
            'hard' => 4,
            'good' => 7,
            default => 10,
        };

        $ease = (float) ($card->ease ?? 2.5);
        $difficultyFactor = max(0.8, 1 + max(0.0, 2.5 - $ease));

        $repeatCount = $this->sessionStats[$card->id][$rating] ?? 0;
        $repeatFactor = 1 + ($repeatCount * 0.4);

        $spacing = (int) round(($base * $repeatFactor) / $difficultyFactor);

        return max(1, $spacing);
    }

    private function bumpSessionStat(string $cardId, string $rating): void
    {
        if (! isset($this->sessionStats[$cardId])) {
            $this->sessionStats[$cardId] = [
                'again' => 0,
                'hard' => 0,
                'good' => 0,
            ];
        }

        if (isset($this->sessionStats[$cardId][$rating])) {
            $this->sessionStats[$cardId][$rating]++;
        }
    }

    /**
     * @return array<int, string>
     */
    private function queuedCardIds(): array
    {
        $ids = [];

        foreach ($this->queue as $item) {
            $ids[] = $item['card_id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, string> $excludeIds
     *
     * @return \Illuminate\Support\Collection<int, Card>
     */
    private function dueCards(int $limit, array $excludeIds)
    {
        return Card::query()
            ->where('user_id', Auth::id())
            ->where('deck_id', $this->deckId)
            ->where('status', CardStatus::Approved)
            ->when($excludeIds !== [], function ($builder) use ($excludeIds) {
                $builder->whereNotIn('id', $excludeIds);
            })
            ->where(function ($builder) {
                $builder->whereNull('due_at')
                    ->orWhere('due_at', '<=', now());
            })
            ->orderBy('due_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param array<int, string> $excludeIds
     *
     * @return \Illuminate\Support\Collection<int, Card>
     */
    private function recentCards(int $limit, array $excludeIds)
    {
        return Card::query()
            ->where('user_id', Auth::id())
            ->where('deck_id', $this->deckId)
            ->where('status', CardStatus::Approved)
            ->when($excludeIds !== [], function ($builder) use ($excludeIds) {
                $builder->whereNotIn('id', $excludeIds);
            })
            ->whereNotNull('last_reviewed_at')
            ->where('last_reviewed_at', '>=', now()->subDays(7))
            ->orderByDesc('last_reviewed_at')
            ->limit($limit)
            ->get();
    }

    private function assistKey(string $cardId, string $mode): string
    {
        return $cardId.'-'.$mode;
    }
}
