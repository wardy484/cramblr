# Anki-Style Learning Steps Implementation Plan

## Overview
Implement Anki-style learning steps for study sessions, allowing cards to progress through graduated intervals before entering long-term review. Learning steps govern intra-session and short-term scheduling until graduation, then SM-2 handles long-term scheduling.

## Data Model Changes

### 1. Cards Table Migration
**File:** `database/migrations/YYYY_MM_DD_HHMMSS_add_learning_step_fields_to_cards_table.php`

Add columns:
- `learning_step_index` (integer, nullable) - Current step index (0-based)
- `is_learning` (boolean, default false) - Whether card is in learning phase
- `is_relearning` (boolean, default false) - Whether card is in relearning phase

### 2. Deck Settings Schema
**File:** `app/Models/Deck.php` (no changes needed, uses JSON `study_settings`)

Add to `study_settings` JSON:
- `learning_steps_enabled` (boolean, default false)
- `learning_steps` (array, default: `["1m", "10m", "1d"]`) - Array of step intervals
- `relearning_steps` (array, default: `["10m"]`) - Array of relearning step intervals
- `again_delay_cards` (integer, default: 0) - Number of cards to show before requeuing "Again" (0 = immediate)

## Service Layer Changes

### 3. Scheduler Service
**File:** `app/Services/Study/Scheduler.php`

**New Methods:**
- `scheduleWithLearningSteps(Card $card, string $rating, array $settings): array`
  - Main entry point when learning steps enabled
  - Routes to learning/relearning/review logic based on card state
  
- `handleLearningStep(Card $card, string $rating, array $settings): array`
  - Handles "Again", "Hard", "Good", "Easy" for cards in learning
  - Advances step index, schedules next due date, handles graduation
  
- `handleRelearningStep(Card $card, string $rating, array $settings): array`
  - Handles "Again", "Hard", "Good", "Easy" for cards in relearning
  - Similar to learning but uses relearning steps
  
- `handleReview(Card $card, string $rating, array $settings): array`
  - Existing SM-2 logic for graduated cards (unchanged)
  
- `parseStepInterval(string $step): int`
  - Parses step strings like "1m", "10m", "1d" to minutes
  - Returns minutes as integer
  
- `getNextStepInterval(Card $card, array $steps): ?int`
  - Gets the next step interval in minutes
  - Returns null if at final step (ready to graduate)

**Modified Methods:**
- `scheduleReview()` - Check if learning steps enabled, route accordingly

**Behavior:**
- **Again (learning):** Reset to step 0, schedule first step interval
- **Hard (learning):** Move back one step (or stay at 0), schedule shorter interval
- **Good (learning):** Advance to next step, schedule that step's interval. If at final step, graduate to review with SM-2
- **Easy (learning):** Graduate immediately to review with longer SM-2 interval
- **Again (relearning):** Reset to relearning step 0, schedule first relearning step
- **Hard/Good/Easy (relearning):** Similar logic but with relearning steps

### 4. Learning Step Helper Service (Optional)
**File:** `app/Services/Study/LearningStepHelper.php` (new)

Helper methods for:
- Parsing step intervals
- Calculating next due dates
- Determining if card should graduate
- Formatting step display strings

## Component Changes

### 5. StudySession Component
**File:** `app/Livewire/StudySession.php`

**Modified Methods:**
- `rate()` - Check if card is in learning/relearning, handle requeue logic differently
  - "Again": Requeue immediately or after N cards based on `again_delay_cards`
  - "Hard": Requeue within session (existing logic)
  - "Good": Requeue later in session if not graduating
  - "Easy": Don't requeue (graduating)

- `requeueCard()` - Update logic to respect learning step requeue rules

- `mergeSettings()` - Add learning step defaults

- `settingsDefaults()` - Add learning step default values

**New Methods:**
- `currentStepInfo()` - Returns current step index and total steps for display
- `predictedNextInterval()` - Returns predicted next interval for display

**New Properties:**
- None needed (use computed properties)

### 6. StudySession View
**File:** `resources/views/livewire/study-session.blade.php`

**Changes:**
- Add learning step settings to settings modal:
  - Toggle for `learning_steps_enabled`
  - Input for `learning_steps` (comma-separated or array input)
  - Input for `relearning_steps` (comma-separated or array input)
  - Input for `again_delay_cards` (number)
  
- Display step progress above rating buttons:
  - "Step 2 of 3" when in learning/relearning
  - "Review" when in review state
  
- Display predicted next interval:
  - "Next: 10 minutes" or "Next: 1 day" etc.

### 7. LearnSession Component (if needed)
**File:** `app/Livewire/LearnSession.php`

**Changes:**
- Update `markLearned()` to use learning steps if enabled
- Cards should start at learning step 0 when first learned

## Test Updates

### 8. Scheduler Tests
**File:** `tests/Unit/SchedulerTest.php`

**New Tests:**
- Learning step progression: Again → Hard → Good → Good (graduation)
- Learning step: Easy graduates immediately
- Relearning step progression
- Step interval parsing (1m, 10m, 1d, etc.)
- Learning steps disabled falls back to SM-2

### 9. StudySession Tests
**File:** `tests/Feature/StudySessionTest.php`

**New Tests:**
- "Again" requeues immediately when `again_delay_cards = 0`
- "Again" requeues after N cards when `again_delay_cards > 0`
- "Hard" requeues within session during learning
- "Good" advances step and requeues during learning
- "Good" graduates and doesn't requeue when at final step
- "Easy" graduates immediately and doesn't requeue
- Step display shows correct step number
- Predicted interval displays correctly

### 10. Integration Tests
**File:** `tests/Feature/LearningStepsTest.php` (new)

**Tests:**
- Full learning flow: new card → step 1 → step 2 → step 3 → review
- Relearning flow: review card → again → relearning steps → review
- Settings persistence
- Multiple cards in queue with different step states

## Implementation Order

1. **Migration** - Add learning step fields to cards table
2. **Scheduler** - Implement learning step logic
3. **StudySession** - Update rate() and requeue logic
4. **UI Settings** - Add learning step settings to modal
5. **UI Display** - Show step progress and predicted interval
6. **Tests** - Write and update tests
7. **LearnSession** - Update if needed for new cards

## Edge Cases to Handle

- Cards with `learning_step_index` but `is_learning = false` (migration cleanup)
- Empty learning_steps array (disable learning steps)
- Step intervals that are invalid (fallback to default)
- Cards transitioning from learning to review (graduation)
- Cards transitioning from review to relearning (lapse)
- `again_delay_cards` larger than queue size (requeue immediately)

## Default Values

- `learning_steps_enabled`: false (opt-in feature)
- `learning_steps`: ["1m", "10m", "1d"]
- `relearning_steps`: ["10m"]
- `again_delay_cards`: 0 (immediate requeue)

## Notes

- Learning steps only apply when `learning_steps_enabled = true`
- SM-2 continues to handle long-term scheduling after graduation
- Learning steps govern intra-session and short-term (minutes/hours/days) scheduling
- Cards start as `is_learning = true, learning_step_index = 0` when first learned
- Cards become `is_relearning = true` when they lapse during review
