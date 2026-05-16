<?php

namespace App\Enums;

enum MealTimeSlot: string
{
    case BREAKFAST  = 'breakfast';
    case LUNCH      = 'lunch';
    case SNACK      = 'snack';
    case DINNER     = 'dinner';
    case LATE_NIGHT = 'late_night';
}
