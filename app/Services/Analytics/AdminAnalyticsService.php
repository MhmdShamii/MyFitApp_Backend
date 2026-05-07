<?php

namespace App\Services\Analytics;

use App\Enums\CoachApplicationStatus;
use App\Models\CoachApplication;
use App\Models\DailySummary;
use App\Models\PlatformDailyStat;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    public function overview(): array
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayStat     = PlatformDailyStat::find($today->toDateString());
        $yesterdayStat = PlatformDailyStat::find($yesterday->toDateString());

        return [
            'users'              => $this->userStats($todayStat, $yesterdayStat),
            'coach_applications' => $this->coachApplicationStats(),
            'meals_logged'       => $this->mealsLoggedStats($todayStat, $yesterdayStat),
            'active_users'       => $this->activeUserStats($today, $yesterday),
            'logged_in_users'    => $this->loggedInUsersCount(),
        ];
    }

    private function userStats(?PlatformDailyStat $today, ?PlatformDailyStat $yesterday): array
    {
        $newToday     = $today?->new_users ?? 0;
        $newYesterday = $yesterday?->new_users ?? 0;

        return [
            'total'          => User::count(),
            'new_today'      => $newToday,
            'new_yesterday'  => $newYesterday,
            'change_percent' => $this->percentChange($newYesterday, $newToday),
            'history'        => $this->statsHistory('new_users'),
        ];
    }

    private function coachApplicationStats(): array
    {
        $counts = CoachApplication::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'pending'  => (int) ($counts[CoachApplicationStatus::PENDING->value]  ?? 0),
            'approved' => (int) ($counts[CoachApplicationStatus::APPROVED->value] ?? 0),
            'rejected' => (int) ($counts[CoachApplicationStatus::REJECTED->value] ?? 0),
            'total'    => $counts->sum(),
        ];
    }

    private function mealsLoggedStats(?PlatformDailyStat $today, ?PlatformDailyStat $yesterday): array
    {
        $todayCount     = $today?->meals_logged ?? 0;
        $yesterdayCount = $yesterday?->meals_logged ?? 0;

        return [
            'today'          => $todayCount,
            'yesterday'      => $yesterdayCount,
            'change_percent' => $this->percentChange($yesterdayCount, $todayCount),
            'history'        => $this->statsHistory('meals_logged'),
        ];
    }

    private function activeUserStats(Carbon $today, Carbon $yesterday): array
    {
        $todayCount     = DailySummary::whereDate('date', $today)->where('logs_count', '>', 0)->count();
        $yesterdayCount = DailySummary::whereDate('date', $yesterday)->where('logs_count', '>', 0)->count();

        return [
            'today'          => $todayCount,
            'yesterday'      => $yesterdayCount,
            'change_percent' => $this->percentChange($yesterdayCount, $todayCount),
            'history'        => $this->activeUsersHistory(),
        ];
    }

    private function loggedInUsersCount(): array
    {
        $count = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereDate('last_used_at', Carbon::today())
            ->distinct('tokenable_id')
            ->count('tokenable_id');

        return ['count' => $count];
    }

    private function statsHistory(string $column): array
    {
        return PlatformDailyStat::where('date', '>=', Carbon::now()->subDays(29)->toDateString())
            ->orderBy('date')
            ->get(['date', $column])
            ->map(fn($r) => ['date' => $r->date, 'count' => (int) $r->$column])
            ->toArray();
    }

    private function activeUsersHistory(): array
    {
        return DailySummary::selectRaw("date, COUNT(*) as count")
            ->where('logs_count', '>', 0)
            ->where('date', '>=', Carbon::now()->subDays(29)->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->toArray();
    }

    private function percentChange(int $previous, int $current): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
