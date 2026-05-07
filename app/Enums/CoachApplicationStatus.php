<?php

namespace App\Enums;

enum CoachApplicationStatus: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
