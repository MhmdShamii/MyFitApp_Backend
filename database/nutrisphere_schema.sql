-- =============================================================================
-- NutriSphere – Full Database Schema
-- =============================================================================
-- Database: MySQL 8+  (the recommendation_feedback.embedding column requires
--           the pgvector extension and is PostgreSQL-only; see that table's
--           comment for the raw ALTER statement to run separately on Postgres)
-- =============================================================================


-- =============================================================================
-- SECTION 1 — CORE / INFRASTRUCTURE
-- =============================================================================

-- countries
-- Reference table for ISO country data.  Users pick their country during
-- onboarding step 1 (MAIN_INFO).
CREATE TABLE countries (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255)    NOT NULL,
    code       VARCHAR(3)      NOT NULL,   -- ISO 3166-1 alpha-2/3
    phone_code VARCHAR(5)      NOT NULL,

    UNIQUE KEY uq_countries_code (code)
);


-- users
-- Central identity record.  Stores authentication info, role, onboarding
-- progress, and avatar/cover images.  Supports local and OAuth (Google) login.
CREATE TABLE users (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name          VARCHAR(255)                                     NULL,
    last_name           VARCHAR(255)                                     NULL,
    email               VARCHAR(255)                                     NOT NULL,
    provider            ENUM('local','google')                           NOT NULL DEFAULT 'local',
    provider_id         VARCHAR(255)                                     NULL,
    role                ENUM('client','coach','admin')                   NOT NULL DEFAULT 'client',
    onboarding_step     ENUM('main_info','basic_info','targets','health_conditions','complete')
                                                                         NOT NULL DEFAULT 'main_info',
    country_id          BIGINT UNSIGNED                                  NULL,
    email_verified_at   TIMESTAMP                                        NULL,
    password            VARCHAR(255)                                     NULL,
    remember_token      VARCHAR(100)                                     NULL,
    followers_count     INT UNSIGNED                                     NOT NULL DEFAULT 0,
    following_count     INT UNSIGNED                                     NOT NULL DEFAULT 0,
    image               VARCHAR(255)                                     NULL     DEFAULT 'default.png',
    cover_image         VARCHAR(255)                                     NULL     DEFAULT 'default_cover.png',
    created_at          TIMESTAMP                                        NULL,
    updated_at          TIMESTAMP                                        NULL,

    UNIQUE KEY uq_users_email                (email),
    UNIQUE KEY uq_users_provider_provider_id (provider, provider_id),

    CONSTRAINT fk_users_country
        FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE RESTRICT
);


-- password_reset_tokens
-- Stores one-time tokens used for the "forgot password" flow.
CREATE TABLE password_reset_tokens (
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NULL,

    PRIMARY KEY (email)
);


-- sessions
-- Laravel session driver storage (database driver).
CREATE TABLE sessions (
    id            VARCHAR(255)    NOT NULL,
    user_id       BIGINT UNSIGNED NULL,
    ip_address    VARCHAR(45)     NULL,
    user_agent    TEXT            NULL,
    payload       LONGTEXT        NOT NULL,
    last_activity INT             NOT NULL,

    PRIMARY KEY  (id),
    INDEX idx_sessions_user_id       (user_id),
    INDEX idx_sessions_last_activity (last_activity)
);


-- cache
-- Laravel cache driver storage (database driver).
CREATE TABLE cache (
    `key`      VARCHAR(255) NOT NULL,
    value      MEDIUMTEXT   NOT NULL,
    expiration INT          NOT NULL,

    PRIMARY KEY (`key`)
);


-- cache_locks
-- Distributed lock records used by Laravel's atomic lock mechanism.
CREATE TABLE cache_locks (
    `key`      VARCHAR(255) NOT NULL,
    owner      VARCHAR(255) NOT NULL,
    expiration INT          NOT NULL,

    PRIMARY KEY (`key`)
);


-- jobs
-- Laravel queue job storage (database queue driver).
CREATE TABLE jobs (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    queue        VARCHAR(255)     NOT NULL,
    payload      LONGTEXT         NOT NULL,
    attempts     TINYINT UNSIGNED NOT NULL,
    reserved_at  INT UNSIGNED     NULL,
    available_at INT UNSIGNED     NOT NULL,
    created_at   INT UNSIGNED     NOT NULL,

    INDEX idx_jobs_queue (queue)
);


-- job_batches
-- Tracks batches of queued jobs (Laravel Bus::batch).
CREATE TABLE job_batches (
    id             VARCHAR(255) NOT NULL,
    name           VARCHAR(255) NOT NULL,
    total_jobs     INT          NOT NULL,
    pending_jobs   INT          NOT NULL,
    failed_jobs    INT          NOT NULL,
    failed_job_ids LONGTEXT     NOT NULL,
    options        MEDIUMTEXT   NULL,
    cancelled_at   INT          NULL,
    created_at     INT          NOT NULL,
    finished_at    INT          NULL,

    PRIMARY KEY (id)
);


-- failed_jobs
-- Archive of queue jobs that exhausted retry attempts.
CREATE TABLE failed_jobs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uuid       VARCHAR(255)    NOT NULL,
    connection TEXT            NOT NULL,
    queue      TEXT            NOT NULL,
    payload    LONGTEXT        NOT NULL,
    exception  LONGTEXT        NOT NULL,
    failed_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_failed_jobs_uuid (uuid)
);


-- personal_access_tokens
-- Laravel Sanctum API tokens.  Polymorphic so any model can own tokens.
CREATE TABLE personal_access_tokens (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255)    NOT NULL,   -- morph type (e.g. App\Models\User)
    tokenable_id   BIGINT UNSIGNED NOT NULL,   -- morph id
    name           TEXT            NOT NULL,
    token          VARCHAR(64)     NOT NULL,
    abilities      TEXT            NULL,
    last_used_at   TIMESTAMP       NULL,
    expires_at     TIMESTAMP       NULL,
    created_at     TIMESTAMP       NULL,
    updated_at     TIMESTAMP       NULL,

    UNIQUE KEY uq_pat_token  (token),
    INDEX idx_pat_tokenable  (tokenable_type, tokenable_id),
    INDEX idx_pat_expires_at (expires_at)
);


-- =============================================================================
-- SECTION 2 — USER PROFILE & HEALTH
-- =============================================================================

-- user_profile
-- Extended health/fitness attributes collected during onboarding steps 2–3.
-- Stores TDEE inputs (weight, height, activity level, goal) and the
-- calculated daily macro targets.  One-to-one with users.
CREATE TABLE user_profile (
    id                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id               BIGINT UNSIGNED  NOT NULL,
    date_of_birth         DATE             NULL,
    gender                ENUM('male','female') NULL,
    weight_kg             DECIMAL(5,2)     NULL,
    height_cm             DECIMAL(5,2)     NULL,
    body_fat_pct          DECIMAL(5,2)     NULL,
    activity_level        ENUM('sedentary','light','moderate','active','very_active') NULL,
    goal                  ENUM('lose_weight','gain_muscle','maintain') NULL,
    dietary_preferences   ENUM('vegetarian','vegan','pescatarian','none') NOT NULL DEFAULT 'none',
    daily_calorie_target  INT UNSIGNED     NULL,
    daily_protein_g       INT UNSIGNED     NULL,
    daily_carbs_g         INT UNSIGNED     NULL,
    daily_fat_g           INT UNSIGNED     NULL,
    created_at            TIMESTAMP        NULL,
    updated_at            TIMESTAMP        NULL,

    UNIQUE KEY uq_user_profile_user_id (user_id),

    CONSTRAINT fk_user_profile_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);


-- health_conditions
-- Catalogue of predefined health conditions (diseases, allergies,
-- intolerances, conditions) with a severity level that drives how the AI
-- assistant adapts meal recommendations.
CREATE TABLE health_conditions (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255)    NOT NULL,
    slug       VARCHAR(255)    NOT NULL,
    type       ENUM('disease','allergy','intolerance','condition') NOT NULL,
    severity   ENUM('block','warn','adjust') NOT NULL,   -- block=never suggest, warn=alert, adjust=tweak macros
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,

    UNIQUE KEY uq_health_conditions_slug (slug)
);


-- user_health_conditions
-- Pivot table linking users to their health conditions.  A user may also
-- enter a free-text custom condition if it is not in the catalogue.
CREATE TABLE user_health_conditions (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id              BIGINT UNSIGNED NOT NULL,
    health_condition_id  BIGINT UNSIGNED NULL,   -- NULL when custom_condition is used
    custom_condition     VARCHAR(255)    NULL,
    created_at           TIMESTAMP       NULL,
    updated_at           TIMESTAMP       NULL,

    UNIQUE KEY uq_uhc_user_condition (user_id, health_condition_id),

    CONSTRAINT fk_uhc_user
        FOREIGN KEY (user_id)             REFERENCES users             (id) ON DELETE CASCADE,
    CONSTRAINT fk_uhc_condition
        FOREIGN KEY (health_condition_id) REFERENCES health_conditions (id) ON DELETE CASCADE
);


-- user_weight_logs
-- Time-series log of a user's body weight.  One entry per calendar day.
-- Used to track progress toward weight goals.
CREATE TABLE user_weight_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    weight_kg  DECIMAL(5,2)   NOT NULL,
    logged_at  DATE            NOT NULL,
    note       VARCHAR(255)    NULL,
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,

    UNIQUE KEY uq_weight_logs_user_date (user_id, logged_at),

    CONSTRAINT fk_weight_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);


-- =============================================================================
-- SECTION 3 — MEALS & NUTRITION
-- =============================================================================

-- ingredients
-- Global ingredient dictionary.  Entries can be seeded by the system or
-- contributed by users (source = 'user').  Verified entries are trusted by
-- the AI macro estimator.
CREATE TABLE ingredients (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name_en    VARCHAR(255)    NOT NULL,
    name_ar    VARCHAR(255)    NULL,
    source     ENUM('system','user') NOT NULL DEFAULT 'user',
    verified   TINYINT(1)      NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,

    INDEX idx_ingredients_name_en (name_en),
    INDEX idx_ingredients_name_ar (name_ar)
);


-- meal_macros
-- De-duplicated macro snapshot for a specific ingredient combination.
-- Identified by a deterministic fingerprint so identical recipes share a row.
-- Allows fast macro lookup without re-computing per meal post.
CREATE TABLE meal_macros (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fingerprint VARCHAR(64)     NOT NULL,   -- SHA-256 hash of sorted ingredient+portion list
    calories    DECIMAL(8,2)    NOT NULL,
    protein     DECIMAL(8,2)    NOT NULL,
    carbs       DECIMAL(8,2)    NOT NULL,
    fats        DECIMAL(8,2)    NOT NULL,
    fiber       DECIMAL(8,2)    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NULL,
    updated_at  TIMESTAMP       NULL,

    UNIQUE KEY uq_meal_macros_fingerprint (fingerprint)
);


-- meal_posts
-- A user-created meal recipe shared on the social feed.  Links to its macro
-- snapshot via fingerprint.  Supports public/private visibility and soft
-- delete.  Denormalized counters (likes, relogs, comments, saves) are kept in
-- sync by application-level events.
CREATE TABLE meal_posts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_profile_id BIGINT UNSIGNED NOT NULL,
    fingerprint     VARCHAR(64)     NOT NULL,   -- FK to meal_macros.fingerprint
    name            VARCHAR(255)    NOT NULL,
    description     TEXT            NULL,
    visibility      ENUM('public','private') NOT NULL DEFAULT 'public',
    servings        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    image_url       VARCHAR(255)    NULL,
    confirmed_at    TIMESTAMP       NULL,        -- set when admin/system confirms the macro data
    likes_count     INT UNSIGNED    NOT NULL DEFAULT 0,
    relogs_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    comments_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    deleted_at      TIMESTAMP       NULL,        -- soft delete
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,

    INDEX idx_meal_posts_user_profile_id (user_profile_id),
    INDEX idx_meal_posts_visibility      (visibility),
    INDEX idx_meal_posts_deleted_at      (deleted_at),

    CONSTRAINT fk_meal_posts_user_profile
        FOREIGN KEY (user_profile_id) REFERENCES user_profile (id) ON DELETE RESTRICT,
    CONSTRAINT fk_meal_posts_macros
        FOREIGN KEY (fingerprint) REFERENCES meal_macros (fingerprint) ON DELETE RESTRICT
);


-- meal_ingredients
-- Many-to-many join between a meal post and its ingredients, storing the
-- portion size and unit for each ingredient in that recipe.
CREATE TABLE meal_ingredients (
    meal_post_id  BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED NOT NULL,
    portion       DECIMAL(8,2)   NOT NULL,
    unit          VARCHAR(50)    NOT NULL,

    PRIMARY KEY (meal_post_id, ingredient_id),

    CONSTRAINT fk_meal_ingredients_meal_post
        FOREIGN KEY (meal_post_id)  REFERENCES meal_posts  (id) ON DELETE CASCADE,
    CONSTRAINT fk_meal_ingredients_ingredient
        FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE RESTRICT
);


-- meal_preparation_steps
-- Ordered preparation instructions for a meal post.  Each row is one step
-- with a sequential step_number per meal.
CREATE TABLE meal_preparation_steps (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    meal_post_id BIGINT UNSIGNED  NOT NULL,
    step_number  INT UNSIGNED     NOT NULL,
    description  TEXT             NOT NULL,
    created_at   TIMESTAMP        NULL,
    updated_at   TIMESTAMP        NULL,

    INDEX idx_meal_prep_steps_meal_post (meal_post_id),

    CONSTRAINT fk_meal_prep_steps_meal_post
        FOREIGN KEY (meal_post_id) REFERENCES meal_posts (id) ON DELETE CASCADE
);


-- daily_summaries
-- Aggregated nutrition totals for a user on a given calendar day.  Acts as
-- the rolled-up view updated whenever a daily_log entry is added or removed.
-- One row per user per date.
CREATE TABLE daily_summaries (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id            BIGINT UNSIGNED NOT NULL,
    date               DATE            NOT NULL,
    calories_consumed  DECIMAL(8,2)   NOT NULL DEFAULT 0,
    protein_consumed   DECIMAL(8,2)   NOT NULL DEFAULT 0,
    carbs_consumed     DECIMAL(8,2)   NOT NULL DEFAULT 0,
    fats_consumed      DECIMAL(8,2)   NOT NULL DEFAULT 0,
    fiber_consumed     DECIMAL(8,2)   NOT NULL DEFAULT 0,
    calories_target    DECIMAL(8,2)   NOT NULL DEFAULT 0,
    protein_target     DECIMAL(8,2)   NOT NULL DEFAULT 0,
    carbs_target       DECIMAL(8,2)   NOT NULL DEFAULT 0,
    fats_target        DECIMAL(8,2)   NOT NULL DEFAULT 0,
    fiber_target       DECIMAL(8,2)   NOT NULL DEFAULT 0,
    logs_count         INT UNSIGNED   NOT NULL DEFAULT 0,
    created_at         TIMESTAMP       NULL,
    updated_at         TIMESTAMP       NULL,

    UNIQUE KEY uq_daily_summaries_user_date (user_id, date),

    CONSTRAINT fk_daily_summaries_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);


-- daily_logs
-- Individual food-consumption events logged throughout the day.  Each row is
-- one entry: a full meal post relog, a custom named food, or an AI estimate.
-- Rolled up into daily_summaries on save.
CREATE TABLE daily_logs (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id           BIGINT UNSIGNED  NOT NULL,
    daily_summary_id  BIGINT UNSIGNED  NOT NULL,
    logged_at         DATETIME         NOT NULL,
    confirmed_at      TIMESTAMP        NULL,        -- set after user confirms an AI estimate
    type              ENUM('meal','custom','estimate') NOT NULL,
    meal_post_id      BIGINT UNSIGNED  NULL,        -- populated when type = 'meal'
    log_name          VARCHAR(255)     NULL,        -- populated when type = 'custom' | 'estimate'
    fingerprint       VARCHAR(255)     NULL,        -- macro fingerprint for custom/estimate entries
    description       TEXT             NULL,
    calories          DECIMAL(8,2)    NOT NULL,
    protein           DECIMAL(8,2)    NOT NULL,
    carbs             DECIMAL(8,2)    NOT NULL,
    fats              DECIMAL(8,2)    NOT NULL,
    fiber             DECIMAL(8,2)    NOT NULL,
    created_at        TIMESTAMP        NULL,
    updated_at        TIMESTAMP        NULL,

    CONSTRAINT fk_daily_logs_user
        FOREIGN KEY (user_id)          REFERENCES users           (id) ON DELETE CASCADE,
    CONSTRAINT fk_daily_logs_summary
        FOREIGN KEY (daily_summary_id) REFERENCES daily_summaries (id) ON DELETE CASCADE,
    CONSTRAINT fk_daily_logs_meal_post
        FOREIGN KEY (meal_post_id)     REFERENCES meal_posts       (id) ON DELETE SET NULL
);


-- =============================================================================
-- SECTION 4 — SOCIAL
-- =============================================================================

-- user_follows
-- Directional follow relationship between users (follower → followed).
-- follower_id follows followed_id.  Compound unique key prevents duplicates.
CREATE TABLE user_follows (
    follower_id BIGINT UNSIGNED NOT NULL,
    followed_id BIGINT UNSIGNED NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_follows (follower_id, followed_id),

    CONSTRAINT fk_user_follows_follower
        FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_follows_followed
        FOREIGN KEY (followed_id) REFERENCES users (id) ON DELETE CASCADE
);


-- meal_post_likes
-- Records which users liked which meal posts.  Timestamp-only pivot (no
-- updated_at) because likes are either present or absent.
CREATE TABLE meal_post_likes (
    user_id      BIGINT UNSIGNED NOT NULL,
    meal_post_id BIGINT UNSIGNED NOT NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_meal_post_likes (user_id, meal_post_id),

    CONSTRAINT fk_meal_post_likes_user
        FOREIGN KEY (user_id)      REFERENCES users      (id) ON DELETE CASCADE,
    CONSTRAINT fk_meal_post_likes_meal_post
        FOREIGN KEY (meal_post_id) REFERENCES meal_posts (id) ON DELETE CASCADE
);


-- meal_post_saves
-- Records which users bookmarked/saved which meal posts for later reference.
CREATE TABLE meal_post_saves (
    user_id      BIGINT UNSIGNED NOT NULL,
    meal_post_id BIGINT UNSIGNED NOT NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, meal_post_id),

    CONSTRAINT fk_meal_post_saves_user
        FOREIGN KEY (user_id)      REFERENCES users      (id) ON DELETE CASCADE,
    CONSTRAINT fk_meal_post_saves_meal_post
        FOREIGN KEY (meal_post_id) REFERENCES meal_posts (id) ON DELETE CASCADE
);


-- meal_post_comments
-- Threaded comments on meal posts.  parent_id enables one level of nesting
-- (replies).  Deleting a parent cascades to its replies.
CREATE TABLE meal_post_comments (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    meal_post_id BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NOT NULL,
    parent_id    BIGINT UNSIGNED NULL,    -- NULL = top-level comment; non-NULL = reply
    body         TEXT            NOT NULL,
    created_at   TIMESTAMP       NULL,
    updated_at   TIMESTAMP       NULL,

    CONSTRAINT fk_comments_meal_post
        FOREIGN KEY (meal_post_id) REFERENCES meal_posts         (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)      REFERENCES users              (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_id)    REFERENCES meal_post_comments (id) ON DELETE CASCADE
);


-- notifications
-- In-app notification feed.  Each row targets one user (user_id) and records
-- who triggered it (actor_id).  The JSON data column carries type-specific
-- payload (e.g. meal_post_id for a like).  read_at is NULL until the user
-- opens the notification.
CREATE TABLE notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,    -- recipient
    actor_id   BIGINT UNSIGNED NOT NULL,    -- who triggered the notification
    type       ENUM(
        'like',
        'comment',
        'reply',
        'relog',
        'follow',
        'coach_application',
        'coach_application_approved',
        'coach_application_rejected'
    ) NOT NULL,
    data       JSON            NOT NULL,    -- type-specific context payload
    read_at    TIMESTAMP       NULL,
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,

    INDEX idx_notifications_user_read (user_id, read_at),

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor
        FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE CASCADE
);


-- =============================================================================
-- SECTION 5 — COACHING
-- =============================================================================

-- coach_applications
-- Submitted applications from users who wish to become coaches.  Reviewed by
-- admins who can approve or reject with an optional rejection reason.
-- Reviewed_by is NULLed out if the reviewing admin is later deleted.
CREATE TABLE coach_applications (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    description      TEXT            NOT NULL,   -- applicant's self-description / credentials text
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT            NULL,
    reviewed_by      BIGINT UNSIGNED NULL,        -- admin user who took action
    reviewed_at      TIMESTAMP       NULL,
    created_at       TIMESTAMP       NULL,
    updated_at       TIMESTAMP       NULL,

    CONSTRAINT fk_coach_apps_user
        FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_coach_apps_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
);


-- coach_application_documents
-- Supporting files (certificates, photos) uploaded alongside a coach
-- application.  Multiple documents are allowed per application.
CREATE TABLE coach_application_documents (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    coach_application_id  BIGINT UNSIGNED NOT NULL,
    file_path             VARCHAR(255)    NOT NULL,
    original_name         VARCHAR(255)    NOT NULL,
    type                  ENUM('certificate','image') NOT NULL,
    created_at            TIMESTAMP       NULL,
    updated_at            TIMESTAMP       NULL,

    CONSTRAINT fk_coach_app_docs_application
        FOREIGN KEY (coach_application_id) REFERENCES coach_applications (id) ON DELETE CASCADE
);


-- =============================================================================
-- SECTION 6 — AI ASSISTANT (RAG / NutriBot)
-- =============================================================================

-- conversations
-- One conversation thread per user session with the AI nutrition assistant.
-- last_suggested_meals stores the JSON array of meals the assistant last
-- recommended so the next turn can reference them without re-querying.
CREATE TABLE conversations (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_id            BIGINT UNSIGNED NOT NULL,
    last_suggested_meals  JSON            NULL,   -- cached meal suggestions from last AI turn
    title                 VARCHAR(255)    NULL,   -- auto-generated short title
    summary               TEXT            NULL,   -- rolling compressed summary of the conversation
    last_active_at        TIMESTAMP       NULL,
    created_at            TIMESTAMP       NULL,
    updated_at            TIMESTAMP       NULL,

    CONSTRAINT fk_conversations_profile
        FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE
);


-- conversation_messages
-- Individual turns (user and assistant) within an AI conversation.
-- tokens_used tracks LLM token consumption per message for cost monitoring.
CREATE TABLE conversation_messages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role            ENUM('user','assistant') NOT NULL,
    content         TEXT            NOT NULL,
    tokens_used     INT UNSIGNED    NULL,
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,

    CONSTRAINT fk_conv_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE
);


-- user_assistant_memory
-- Persistent key-value facts the AI assistant stores per user profile between
-- conversations (e.g. "user dislikes cilantro", "goal changed to bulking").
-- Acts as the long-term memory layer for the RAG system.
CREATE TABLE user_assistant_memory (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    `key`      TEXT            NOT NULL,
    value      TEXT            NOT NULL,
    created_at TIMESTAMP       NULL,
    updated_at TIMESTAMP       NULL,

    CONSTRAINT fk_user_asst_memory_profile
        FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE
);


-- recommendation_feedback
-- Logs every meal the AI assistant recommended and what the user did with it
-- (logged, dismissed, saved, etc.).  The embedding column holds a 1536-dim
-- pgvector vector for semantic similarity search — used to avoid re-suggesting
-- disliked meals and to surface similar liked meals.
-- shown_count tracks how many times the same meal has been suggested.
--
-- NOTE: The embedding column is PostgreSQL + pgvector only.
-- Run separately on Postgres after migration:
--   CREATE EXTENSION IF NOT EXISTS vector;
--   ALTER TABLE recommendation_feedback ADD COLUMN embedding vector(1536) NULL;
CREATE TABLE recommendation_feedback (
    id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_id     BIGINT UNSIGNED  NOT NULL,
    meal_title     VARCHAR(255)     NOT NULL,
    meal_post_id   BIGINT UNSIGNED  NULL,         -- references meal_posts if it was a platform meal
    source_type    VARCHAR(20)      NOT NULL,      -- 'platform' | 'ai_generated' | etc.
    action         VARCHAR(20)      NOT NULL DEFAULT 'logged',  -- 'logged' | 'dismissed' | 'saved'
    meal_time_slot VARCHAR(20)      NOT NULL,      -- 'breakfast' | 'lunch' | 'dinner' | 'snack'
    logged_hour    SMALLINT         NOT NULL,      -- 0–23, hour of day when action occurred
    calories       FLOAT            NULL,
    protein        FLOAT            NULL,
    carbs          FLOAT            NULL,
    fats           FLOAT            NULL,
    shown_count    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    -- embedding VECTOR(1536) NULL  -- add via raw SQL on PostgreSQL (see note above)
    created_at     TIMESTAMP        NULL,
    updated_at     TIMESTAMP        NULL,

    INDEX idx_rf_profile_id           (profile_id),
    INDEX idx_rf_profile_time_slot    (profile_id, meal_time_slot),
    INDEX idx_rf_profile_action       (profile_id, action),

    CONSTRAINT fk_rf_profile
        FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE
);


-- =============================================================================
-- SECTION 7 — ADMIN / PLATFORM ANALYTICS
-- =============================================================================

-- platform_daily_stats
-- Aggregate platform-level metrics per calendar day for admin dashboards.
-- new_users and meals_logged are incremented by application events.
CREATE TABLE platform_daily_stats (
    date         DATE         NOT NULL,
    new_users    INT UNSIGNED NOT NULL DEFAULT 0,
    meals_logged INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NULL,
    updated_at   TIMESTAMP    NULL,

    PRIMARY KEY (date)
);
