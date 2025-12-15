-- SMO Social Database Performance Optimization - Missing Indexes
-- This file adds critical indexes to improve query performance on large datasets

-- =============================================
-- CHAT MESSAGE TABLE INDEXES
-- =============================================

-- Add composite index for session_id + created_at (frequent query pattern)
ALTER TABLE `wp_smo_chat_messages`
ADD INDEX `idx_session_created` (`session_id`, `created_at`);

-- Add index for content_type (used for filtering message types)
ALTER TABLE `wp_smo_chat_messages`
ADD INDEX `idx_content_type` (`content_type`);

-- Add index for model_used (used for analytics and filtering by AI model)
ALTER TABLE `wp_smo_chat_messages`
ADD INDEX `idx_model_used` (`model_used`);

-- Add composite index for flagged + moderation_score (used for moderation queries)
ALTER TABLE `wp_smo_chat_messages`
ADD INDEX `idx_flagged_moderation` (`flagged`, `moderation_score`);

-- =============================================
-- AI PROVIDERS TABLE INDEXES
-- =============================================

-- Add index for provider_type (used for filtering by provider category)
ALTER TABLE `wp_smo_ai_providers`
ADD INDEX `idx_provider_type` (`provider_type`);

-- Add index for is_default (used for quick lookup of default provider)
ALTER TABLE `wp_smo_ai_providers`
ADD INDEX `idx_is_default` (`is_default`);

-- Add composite index for status + provider_type (common filtering pattern)
ALTER TABLE `wp_smo_ai_providers`
ADD INDEX `idx_status_type` (`status`, `provider_type`);

-- Add index for base_url (used for connection management)
ALTER TABLE `wp_smo_ai_providers`
ADD INDEX `idx_base_url` (`base_url`(255)); -- Using prefix index for long URL field

-- =============================================
-- AI MODELS TABLE INDEXES
-- =============================================

-- Add composite index for provider_id + model_name (unique lookup pattern)
ALTER TABLE `wp_smo_ai_models`
ADD INDEX `idx_provider_model` (`provider_id`, `model_name`);

-- Add index for status (used for filtering active models)
ALTER TABLE `wp_smo_ai_models`
ADD INDEX `idx_model_status` (`status`);

-- =============================================
-- CHAT SESSIONS TABLE INDEXES
-- =============================================

-- Add composite index for user_id + status (common session filtering)
ALTER TABLE `wp_smo_chat_sessions`
ADD INDEX `idx_user_status` (`user_id`, `status`);

-- Add index for provider_id (used for provider-specific session queries)
ALTER TABLE `wp_smo_chat_sessions`
ADD INDEX `idx_session_provider` (`provider_id`);

-- Add index for last_activity (used for recent sessions)
ALTER TABLE `wp_smo_chat_sessions`
ADD INDEX `idx_last_activity` (`last_activity`);

-- =============================================
-- RATE LIMITING TABLE INDEXES
-- =============================================

-- Add composite index for rate limiting queries
ALTER TABLE `wp_smo_chat_rate_limits`
ADD INDEX `idx_rate_limit_composite` (`user_id`, `provider_id`, `rate_limit_key`, `window_start`);

-- =============================================
-- MODERATION TABLE INDEXES
-- =============================================

-- Add composite index for moderation workflow
ALTER TABLE `wp_smo_chat_moderation`
ADD INDEX `idx_moderation_workflow` (`status`, `reviewed_by`, `reviewed_at`);

-- Add index for content_hash (used for duplicate detection)
ALTER TABLE `wp_smo_chat_moderation`
ADD INDEX `idx_content_hash` (`content_hash`);
