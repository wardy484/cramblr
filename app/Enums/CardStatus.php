<?php

namespace App\Enums;

enum CardStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Archived = 'archived';
}
