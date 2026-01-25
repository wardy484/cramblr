<?php

namespace App\Enums;

enum JobPageStatus: string
{
    case Queued = 'queued';
    case Extracted = 'extracted';
    case Failed = 'failed';
}
