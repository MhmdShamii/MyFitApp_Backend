<?php

namespace App\Services\Analytics;

use App\Models\PlatformDailyStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformStatsService
{
    public function incrementNewUsers(): void
    {
        $this->upsert('new_users', 1);
    }

    public function incrementMealsLogged(): void
    {
        $this->upsert('meals_logged', 1);
    }

    public function decrementMealsLogged(): void
    {
        PlatformDailyStat::where('date', Carbon::today()->toDateString())
            ->where('meals_logged', '>', 0)
            ->decrement('meals_logged');
    }

    private function upsert(string $column, int $amount): void
    {
        $today = Carbon::today()->toDateString();

        DB::statement("
            INSERT INTO platform_daily_stats (date, {$column}, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
            ON CONFLICT (date) DO UPDATE SET {$column} = platform_daily_stats.{$column} + ?, updated_at = NOW()
        ", [$today, $amount, $amount]);
    }
}
