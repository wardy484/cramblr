<?php

namespace App\Services\Study;

use App\Models\Card;
use Carbon\CarbonImmutable;

class Scheduler
{
    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function scheduleReview(Card $card, string $rating, array $settings = []): array
    {
        $learningStepsEnabled = (bool) data_get($settings, 'learning_steps_enabled', false);

        if ($learningStepsEnabled) {
            return $this->scheduleWithLearningSteps($card, $rating, $settings);
        }

        $algorithm = (string) data_get($settings, 'algorithm', 'sm2');
        $algorithm = in_array($algorithm, ['sm2', 'fsrs'], true) ? $algorithm : 'sm2';

        $result = $this->scheduleSm2($card, $rating);
        $result['algorithm'] = $algorithm;

        if ($algorithm === 'fsrs') {
            $result['data'] = [
                'algorithm_used' => 'sm2',
                'fsrs_fallback' => true,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function scheduleWithLearningSteps(Card $card, string $rating, array $settings): array
    {
        $studyState = $card->study_state ?? 'new';
        $isLearning = (bool) ($card->is_learning ?? false);
        $isRelearning = (bool) ($card->is_relearning ?? false);

        if ($isRelearning) {
            return $this->handleRelearningStep($card, $rating, $settings);
        }

        if ($isLearning || $studyState === 'new' || $studyState === null) {
            return $this->handleLearningStep($card, $rating, $settings);
        }

        return $this->handleReview($card, $rating, $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleSm2(Card $card, string $rating): array
    {
        $quality = $this->qualityFromRating($rating);
        $now = CarbonImmutable::now();

        $ease = (float) ($card->ease ?? 2.50);
        $repetitions = (int) ($card->repetitions ?? 0);
        $interval = (int) ($card->interval ?? 0);
        $lapses = (int) ($card->lapses ?? 0);

        $ease = $ease + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        $ease = max(1.30, $ease);

        if ($quality < 3) {
            $repetitions = 0;
            $interval = 1;
            $lapses++;
            $studyState = 'relearning';
        } else {
            $repetitions++;
            $studyState = 'review';

            if ($repetitions === 1) {
                $interval = 1;
            } elseif ($repetitions === 2) {
                $interval = 6;
            } else {
                $interval = (int) max(1, round($interval * $ease));
            }
        }

        return [
            'due_at' => $now->addDays($interval),
            'interval' => $interval,
            'ease' => round($ease, 2),
            'repetitions' => $repetitions,
            'lapses' => $lapses,
            'study_state' => $studyState,
            'reviewed_at' => $now,
            'data' => [],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleLearningStep(Card $card, string $rating, array $settings): array
    {
        $now = CarbonImmutable::now();
        $learningSteps = (array) data_get($settings, 'learning_steps', ['1m', '10m', '1d']);
        $currentStepIndex = (int) ($card->learning_step_index ?? 0);

        return match ($rating) {
            'again' => $this->handleLearningAgain($card, $now, $learningSteps),
            'hard' => $this->handleLearningHard($card, $now, $learningSteps, $currentStepIndex),
            'good' => $this->handleLearningGood($card, $now, $learningSteps, $currentStepIndex, $settings),
            'easy' => $this->handleLearningEasy($card, $now, $settings),
            default => $this->handleLearningGood($card, $now, $learningSteps, $currentStepIndex, $settings),
        };
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleRelearningStep(Card $card, string $rating, array $settings): array
    {
        $now = CarbonImmutable::now();
        $relearningSteps = (array) data_get($settings, 'relearning_steps', ['10m']);
        $currentStepIndex = (int) ($card->learning_step_index ?? 0);

        return match ($rating) {
            'again' => $this->handleRelearningAgain($card, $now, $relearningSteps),
            'hard' => $this->handleRelearningHard($card, $now, $relearningSteps, $currentStepIndex),
            'good' => $this->handleRelearningGood($card, $now, $relearningSteps, $currentStepIndex, $settings),
            'easy' => $this->handleRelearningEasy($card, $now, $settings),
            default => $this->handleRelearningGood($card, $now, $relearningSteps, $currentStepIndex, $settings),
        };
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleReview(Card $card, string $rating, array $settings): array
    {
        $result = $this->scheduleSm2($card, $rating);
        $algorithm = (string) data_get($settings, 'algorithm', 'sm2');
        $result['algorithm'] = in_array($algorithm, ['sm2', 'fsrs'], true) ? $algorithm : 'sm2';

        if ($result['algorithm'] === 'fsrs') {
            $result['data'] = [
                'algorithm_used' => 'sm2',
                'fsrs_fallback' => true,
            ];
        }

        $quality = $this->qualityFromRating($rating);
        if ($quality < 3) {
            $relearningSteps = (array) data_get($settings, 'relearning_steps', ['10m']);
            $result['is_relearning'] = true;
            $result['learning_step_index'] = 0;
            $result['is_learning'] = false;
        } else {
            $result['is_relearning'] = false;
            $result['learning_step_index'] = null;
            $result['is_learning'] = false;
        }

        return $result;
    }

    /**
     * @param array<string> $learningSteps
     *
     * @return array<string, mixed>
     */
    private function handleLearningAgain(Card $card, CarbonImmutable $now, array $learningSteps): array
    {
        $intervalMinutes = $this->parseStepInterval($learningSteps[0] ?? '1m');
        $dueAt = $now->addMinutes($intervalMinutes);

        return [
            'due_at' => $dueAt,
            'interval' => 0,
            'ease' => (float) ($card->ease ?? 2.50),
            'repetitions' => 0,
            'lapses' => (int) ($card->lapses ?? 0),
            'study_state' => 'learning',
            'is_learning' => true,
            'is_relearning' => false,
            'learning_step_index' => 0,
            'reviewed_at' => $now,
            'data' => ['interval_minutes' => $intervalMinutes],
        ];
    }

    /**
     * @param array<string> $learningSteps
     *
     * @return array<string, mixed>
     */
    private function handleLearningHard(Card $card, CarbonImmutable $now, array $learningSteps, int $currentStepIndex): array
    {
        $newStepIndex = max(0, $currentStepIndex - 1);
        $stepInterval = $learningSteps[$newStepIndex] ?? $learningSteps[0] ?? '1m';
        $intervalMinutes = $this->parseStepInterval($stepInterval);
        $dueAt = $now->addMinutes($intervalMinutes);

        return [
            'due_at' => $dueAt,
            'interval' => 0,
            'ease' => max(1.30, ((float) ($card->ease ?? 2.50)) - 0.15),
            'repetitions' => 0,
            'lapses' => (int) ($card->lapses ?? 0),
            'study_state' => 'learning',
            'is_learning' => true,
            'is_relearning' => false,
            'learning_step_index' => $newStepIndex,
            'reviewed_at' => $now,
            'data' => ['interval_minutes' => $intervalMinutes],
        ];
    }

    /**
     * @param array<string> $learningSteps
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleLearningGood(Card $card, CarbonImmutable $now, array $learningSteps, int $currentStepIndex, array $settings): array
    {
        $studyState = $card->study_state ?? 'new';
        $isNewCard = ($studyState === 'new' || $studyState === null) && $currentStepIndex === 0;

        if ($isNewCard) {
            $stepIndex = 0;
        } else {
            $stepIndex = $currentStepIndex + 1;
        }

        if ($stepIndex >= count($learningSteps)) {
            return $this->graduateToReview($card, $now, $settings, false);
        }

        $stepInterval = $learningSteps[$stepIndex] ?? $learningSteps[0] ?? '1m';
        $intervalMinutes = $this->parseStepInterval($stepInterval);
        $dueAt = $now->addMinutes($intervalMinutes);

        return [
            'due_at' => $dueAt,
            'interval' => 0,
            'ease' => (float) ($card->ease ?? 2.50),
            'repetitions' => 0,
            'lapses' => (int) ($card->lapses ?? 0),
            'study_state' => 'learning',
            'is_learning' => true,
            'is_relearning' => false,
            'learning_step_index' => $stepIndex,
            'reviewed_at' => $now,
            'data' => ['interval_minutes' => $intervalMinutes],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleLearningEasy(Card $card, CarbonImmutable $now, array $settings): array
    {
        return $this->graduateToReview($card, $now, $settings, true);
    }

    /**
     * @param array<string> $relearningSteps
     *
     * @return array<string, mixed>
     */
    private function handleRelearningAgain(Card $card, CarbonImmutable $now, array $relearningSteps): array
    {
        $intervalMinutes = $this->parseStepInterval($relearningSteps[0] ?? '10m');
        $dueAt = $now->addMinutes($intervalMinutes);

        return [
            'due_at' => $dueAt,
            'interval' => 1,
            'ease' => max(1.30, ((float) ($card->ease ?? 2.50)) - 0.20),
            'repetitions' => 0,
            'lapses' => (int) ($card->lapses ?? 0) + 1,
            'study_state' => 'relearning',
            'is_learning' => false,
            'is_relearning' => true,
            'learning_step_index' => 0,
            'reviewed_at' => $now,
            'data' => ['interval_minutes' => $intervalMinutes],
        ];
    }

    /**
     * @param array<string> $relearningSteps
     *
     * @return array<string, mixed>
     */
    private function handleRelearningHard(Card $card, CarbonImmutable $now, array $relearningSteps, int $currentStepIndex): array
    {
        $newStepIndex = max(0, $currentStepIndex - 1);
        $stepInterval = $relearningSteps[$newStepIndex] ?? $relearningSteps[0] ?? '10m';
        $intervalMinutes = $this->parseStepInterval($stepInterval);
        $dueAt = $now->addMinutes($intervalMinutes);

        return [
            'due_at' => $dueAt,
            'interval' => 1,
            'ease' => max(1.30, ((float) ($card->ease ?? 2.50)) - 0.15),
            'repetitions' => 0,
            'lapses' => (int) ($card->lapses ?? 0),
            'study_state' => 'relearning',
            'is_learning' => false,
            'is_relearning' => true,
            'learning_step_index' => $newStepIndex,
            'reviewed_at' => $now,
            'data' => ['interval_minutes' => $intervalMinutes],
        ];
    }

    /**
     * @param array<string> $relearningSteps
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleRelearningGood(Card $card, CarbonImmutable $now, array $relearningSteps, int $currentStepIndex, array $settings): array
    {
        $nextStepIndex = $currentStepIndex + 1;

        if ($nextStepIndex >= count($relearningSteps)) {
            return $this->graduateToReview($card, $now, $settings, false);
        }

        $stepInterval = $relearningSteps[$nextStepIndex] ?? $relearningSteps[0] ?? '10m';
        $intervalMinutes = $this->parseStepInterval($stepInterval);
        $dueAt = $now->addMinutes($intervalMinutes);

        return [
            'due_at' => $dueAt,
            'interval' => 1,
            'ease' => (float) ($card->ease ?? 2.50),
            'repetitions' => 0,
            'lapses' => (int) ($card->lapses ?? 0),
            'study_state' => 'relearning',
            'is_learning' => false,
            'is_relearning' => true,
            'learning_step_index' => $nextStepIndex,
            'reviewed_at' => $now,
            'data' => ['interval_minutes' => $intervalMinutes],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function handleRelearningEasy(Card $card, CarbonImmutable $now, array $settings): array
    {
        return $this->graduateToReview($card, $now, $settings, true);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function graduateToReview(Card $card, CarbonImmutable $now, array $settings, bool $easy): array
    {
        $ease = (float) ($card->ease ?? 2.50);
        $repetitions = 1;
        $interval = $easy ? 4 : 1;

        if ($easy) {
            $ease = min(2.70, $ease + 0.15);
        }

        $algorithm = (string) data_get($settings, 'algorithm', 'sm2');
        $algorithm = in_array($algorithm, ['sm2', 'fsrs'], true) ? $algorithm : 'sm2';

        $result = [
            'due_at' => $now->addDays($interval),
            'interval' => $interval,
            'ease' => round($ease, 2),
            'repetitions' => $repetitions,
            'lapses' => (int) ($card->lapses ?? 0),
            'study_state' => 'review',
            'is_learning' => false,
            'is_relearning' => false,
            'learning_step_index' => null,
            'reviewed_at' => $now,
            'algorithm' => $algorithm,
            'data' => [],
        ];

        if ($algorithm === 'fsrs') {
            $result['data'] = [
                'algorithm_used' => 'sm2',
                'fsrs_fallback' => true,
            ];
        }

        return $result;
    }

    /**
     * Parse step interval string (e.g., "1m", "10m", "1d") to minutes
     */
    private function parseStepInterval(string $step): int
    {
        $step = strtolower(trim($step));

        if (preg_match('/^(\d+)m$/', $step, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/^(\d+)h$/', $step, $matches)) {
            return (int) $matches[1] * 60;
        }

        if (preg_match('/^(\d+)d$/', $step, $matches)) {
            return (int) $matches[1] * 24 * 60;
        }

        return 1;
    }

    private function qualityFromRating(string $rating): int
    {
        return match ($rating) {
            'again' => 0,
            'hard' => 3,
            'good' => 4,
            'easy' => 5,
            default => 4,
        };
    }
}
