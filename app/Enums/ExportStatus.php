<?php

namespace App\Enums;

enum ExportStatus: string
{
    case Queued = 'queued';
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
