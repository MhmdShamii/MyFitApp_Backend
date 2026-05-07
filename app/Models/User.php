<?php

namespace App\Models;

use App\Enums\UserOnboardingSteps;
use App\Enums\UserProvider;
use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'image',
        'cover_image',
        'first_name',
        'last_name',
        'country_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'provider_id',
        'remember_token',
    ];

    protected $casts = [
        'provider'         => UserProvider::class,
        'role'             => UserRole::class,
        'onboarding_step'  => UserOnboardingSteps::class,
        'email_verified_at' => 'datetime',
    ];

    // Relations
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function healthConditions()
    {
        return $this->hasMany(UserHealthCondition::class);
    }

    public function coachApplication()
    {
        return $this->hasMany(CoachApplication::class);
    }

    public function dailyLogs()
    {
        return $this->hasMany(DailyLog::class);
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'followed_id');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'user_follows', 'followed_id', 'follower_id');
    }

    // Query scopes

    public function scopeFindByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
