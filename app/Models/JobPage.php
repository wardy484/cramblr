<?php

namespace App\Models;

use App\Enums\JobPageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPage extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_id',
        'page_index',
        'image_path',
        'extraction_json',
        'raw_response',
        'confidence',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extraction_json' => 'array',
            'raw_response' => 'array',
            'confidence' => 'float',
            'status' => JobPageStatus::class,
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ExtractionJob::class, 'job_id');
    }
}
