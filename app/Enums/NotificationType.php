<?php

namespace App\Enums;

enum NotificationType: string
{
    case LIKE    = 'like';
    case COMMENT = 'comment';
    case REPLY   = 'reply';
    case RELOG   = 'relog';
    case FOLLOW                    = 'follow';
    case COACH_APPLICATION         = 'coach_application';
    case COACH_APPLICATION_APPROVED = 'coach_application_approved';
    case COACH_APPLICATION_REJECTED = 'coach_application_rejected';
}
