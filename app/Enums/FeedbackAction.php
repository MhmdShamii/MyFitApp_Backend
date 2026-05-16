<?php

namespace App\Enums;

enum FeedbackAction: string
{
    case LOGGED      = 'logged';
    case DISMISSED   = 'dismissed';
    case NOT_CHOSEN  = 'not_chosen';
}
