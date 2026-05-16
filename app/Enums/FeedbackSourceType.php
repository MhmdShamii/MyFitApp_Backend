<?php

namespace App\Enums;

enum FeedbackSourceType: string
{
    case BOT_SUGGESTION = 'bot_suggestion';
    case POST           = 'post';
    case ESTIMATE       = 'estimate';
    case INGREDIENTS    = 'ingredients';
}
