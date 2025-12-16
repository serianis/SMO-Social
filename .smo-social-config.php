<?php
/**
 * SMO Social Configuration
 * 
 * SECURITY NOTICE: Never commit API keys to version control!
 * Use environment variables or WordPress options instead.
 */

// Secure configuration using environment variables
return array (
  'smo_social_huggingface_api_key' => getenv('SMO_SOCIAL_HF_API_KEY') ?: '',
  'smo_social_environment' => getenv('SMO_SOCIAL_ENV') ?: 'production',
  
  // Additional secure configuration options
  'smo_social_debug_mode' => getenv('SMO_SOCIAL_DEBUG') === 'true' ? true : false,
  'smo_social_log_level' => getenv('SMO_SOCIAL_LOG_LEVEL') ?: 'error',
  
  // TODO: Migrate other sensitive configurations to environment variables
  // 'smo_social_database_encryption_key' => getenv('SMO_SOCIAL_DB_ENCRYPTION_KEY'),
  // 'smo_social_oauth_client_secret' => getenv('SMO_SOCIAL_OAUTH_CLIENT_SECRET'),
);
