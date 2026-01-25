<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'deck_id',
        'status',
        'study_state',
        'learning_step_index',
        'is_learning',
        'is_relearning',
        'due_at',
        'interval',
        'ease',
        'repetitions',
        'lapses',
        'last_reviewed_at',
        'front',
        'back',
        'audio_path',
        'tags',
        'extra',
        'source_job_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
            'tags' => 'array',
            'extra' => 'array',
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    public function sourceJob(): BelongsTo
    {
        return $this->belongsTo(ExtractionJob::class, 'source_job_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CardReview::class);
    }
}
