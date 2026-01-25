<?php

namespace App\Models;

use App\Enums\ExtractionJobStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionJob extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'refinement_prompt',
        'translation_preference',
        'import_audio',
        'status',
        'progress_current',
        'progress_total',
        'generation_json',
        'generation_raw',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExtractionJobStatus::class,
            'import_audio' => 'boolean',
            'generation_json' => 'array',
            'generation_raw' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(JobPage::class, 'job_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'source_job_id');
    }
}
