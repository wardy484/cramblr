<?php

namespace App\Enums;

enum ExtractionJobStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
    case Completed = 'completed';
}
