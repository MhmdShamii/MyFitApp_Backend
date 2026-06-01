# NutriSphere Backend

REST API for NutriSphere — a nutrition tracking and social fitness platform. Built with Laravel 12.

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12, PHP 8.4 |
| Auth | Laravel Sanctum 4 |
| Database | MySQL |
| File Storage | AWS S3 |
| Email | Resend |
| AI Chat | OpenAI (via `openai-php/laravel`) |
| OAuth | Google API Client |

## Getting Started

```bash
# 1. Clone and install dependencies
composer install
npm install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Run migrations
php artisan migrate

# 4. Start dev server (server + queue + logs + vite)
composer run dev
```

Or run the full setup in one command:

```bash
composer run setup
```

## Environment Variables

```env
APP_URL=

DB_HOST=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=

OPENAI_API_KEY=

RESEND_API_KEY=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

## API Overview

All routes are prefixed with `/api/v1`.

### Authentication

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/auth/register` | — | Register new user |
| POST | `/auth/login` | — | Login |
| POST | `/auth/google` | — | Google OAuth login |
| GET | `/auth/email/verify/{id}/{hash}` | signed | Verify email address |
| POST | `/auth/email/resend` | — | Resend verification email |
| POST | `/auth/check-email` | — | Check if email exists |
| POST | `/auth/logout` | sanctum | Logout current device |
| POST | `/auth/logout-all` | sanctum | Logout all devices |

### User & Onboarding

New users go through a 4-step onboarding flow before accessing features:

1. **`MAIN_INFO`** — first name, last name, country
2. **`BASIC_INFO`** — date of birth, gender, weight, height, activity level, goal, dietary preferences. TDEE is calculated automatically (Mifflin-St Jeor).
3. **`HEALTH_CONDITIONS`** — add/manage health conditions
4. **`TARGETS`** — confirm or adjust calorie/macro targets → user is `COMPLETE`

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/users/me` | Get current user + profile |
| PATCH | `/users/me` | Update user fields |
| POST | `/users/me/complete-main-info` | Submit step 1 |
| POST | `/users/me/complete-basic-info` | Submit step 2, get calculated targets |
| GET | `/users/me/health-conditions` | Get user health conditions |
| POST | `/users/me/health-conditions` | Add health condition |
| DELETE | `/users/me/health-conditions/{id}` | Remove health condition |
| POST | `/users/me/complete-health-conditions` | Submit step 3 |
| POST | `/users/me/complete-targets` | Submit step 4 (finalize targets) |
| PATCH | `/users/me/targets` | Update nutrition targets post-onboarding |
| POST | `/users/me/avatar` | Upload avatar |
| DELETE | `/users/me/avatar` | Reset avatar |
| POST | `/users/me/cover-image` | Upload cover image |
| DELETE | `/users/me/cover-image` | Reset cover image |

### Nutrition Logging

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/users/me/log/{meal}` | Log a meal |
| POST | `/users/me/log` | Log a custom meal |
| POST | `/users/me/log/estimate` | Log an AI-estimated meal |
| POST | `/users/me/log/{log}/confirm` | Confirm a pending log |
| DELETE | `/users/me/log/{log}` | Remove a log entry |

### Analytics

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/users/me/analytics/today` | Today's logs |
| GET | `/users/me/analytics/today/macros` | Today's macro breakdown |
| GET | `/users/me/analytics/day` | Logs for a specific day |
| GET | `/users/me/analytics/streak` | Current logging streak |
| POST | `/users/me/analytics/weight` | Log body weight |
| GET | `/users/me/analytics/weight` | Weight history |
| GET | `/users/me/analytics/calories` | Calorie intake (weekly) |
| GET | `/users/me/analytics/macros` | Macro intake (weekly) |

### Meals

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/meals/{meal}` | Get meal details |
| POST | `/meals` | Create a meal |
| POST | `/meals/{meal}/confirm` | Confirm (publish) meal |
| POST | `/meals/{meal}/discard` | Discard draft meal |
| POST | `/meals/{meal}/like` | Like a meal |
| DELETE | `/meals/{meal}/like` | Unlike a meal |
| POST | `/meals/{meal}/save` | Save a meal |
| DELETE | `/meals/{meal}/save` | Unsave a meal |
| GET | `/meals/{meal}/comments` | List comments |
| POST | `/meals/{meal}/comments` | Post a comment |
| GET | `/meals/{meal}/comments/{comment}/replies` | List replies |
| POST | `/meals/{meal}/comments/{comment}/replies` | Reply to a comment |
| DELETE | `/meals/{meal}/comments/{comment}` | Delete a comment |

### Social / Feed

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/feed` | Global meal feed |
| GET | `/feed/following` | Feed from followed users |
| GET | `/users/me/saved-meals` | Saved meals |
| GET | `/users/{user}` | Public user profile |
| POST | `/users/{user}/follow` | Follow a user |
| DELETE | `/users/{user}/follow` | Unfollow a user |
| GET | `/users/{user}/followers` | List followers |
| GET | `/users/{user}/following` | List following |
| GET | `/users/{user}/meals` | Public meals by user |
| GET | `/users/{user}/meals/private` | Private meals (own profile) |

### Ingredients

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/ingredients/search` | Search ingredients |

### AI Chat

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/chat` | Send message to AI assistant |
| GET | `/chat/history` | Get chat history |

### Notifications

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/notifications` | List notifications |
| GET | `/notifications/check` | Check for unread notifications |

### Coach Applications

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/coach-application` | Get own application status |
| POST | `/coach-application` | Submit coach application |

### Admin

All admin routes require the `admin` middleware.

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/admin/analytics` | Platform overview stats |
| GET | `/admin/users` | List all users |
| PATCH | `/admin/users/{user}/role` | Update user role |
| GET | `/admin/ingredients` | List unverified ingredients |
| POST | `/admin/ingredients/{ingredient}/approve` | Approve ingredient |
| DELETE | `/admin/ingredients/{ingredient}` | Delete ingredient |
| GET | `/admin/coach-applications` | List coach applications |
| POST | `/admin/coach-applications/{id}/approve` | Approve application |
| POST | `/admin/coach-applications/{id}/reject` | Reject application |

## Running Tests

```bash
composer run test
```

## Deployment

The app is configured for Railway (`railway.toml`). Set all required environment variables in the Railway project dashboard before deploying.
