# SMO Social API Setup Guide

This guide will help you configure API credentials for all supported platforms in SMO Social.

## 🔑 Required API Credentials

### Social Media Platforms

#### Facebook & Instagram (Meta)
1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app or use existing one
3. Add Products: Facebook Login, Instagram Basic Display
4. Configure Valid OAuth Redirect URIs:
   ```
   https://yourdomain.com/wp-admin/admin-ajax.php?action=smo_facebook_oauth_callback
   https://yourdomain.com/wp-json/smo-social/v1/oauth/facebook/callback
   ```
5. Get the following credentials:
   - **App ID**: `SMO_FACEBOOK_APP_ID`
   - **App Secret**: `SMO_FACEBOOK_APP_SECRET`
   - **Page Access Token**: Generated after connecting Facebook Page

#### Twitter/X
1. Go to [Twitter Developer Portal](https://developer.twitter.com/)
2. Apply for developer access
3. Create a new app
4. Go to Keys and Tokens tab
5. Generate:
   - **API Key**: `SMO_TWITTER_API_KEY`
   - **API Secret**: `SMO_TWITTER_API_SECRET`
   - **Access Token**: `SMO_TWITTER_ACCESS_TOKEN`
   - **Access Token Secret**: `SMO_TWITTER_ACCESS_TOKEN_SECRET`

#### LinkedIn
1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Create a new app
3. Add products: Marketing Developer Platform
4. Configure OAuth 2.0 redirect URLs:
   ```
   https://yourdomain.com/wp-admin/admin-ajax.php?action=smo_linkedin_oauth_callback
   ```
5. Get:
   - **Client ID**: `SMO_LINKEDIN_CLIENT_ID`
   - **Client Secret**: `SMO_LINKEDIN_CLIENT_SECRET`

## 🔧 Configuration Methods

### Method 1: Environment Variables (Recommended for Production)

Add to your `wp-config.php` file:

```php
// SMO Social API Credentials
define('SMO_FACEBOOK_APP_ID', 'your_facebook_app_id');
define('SMO_FACEBOOK_APP_SECRET', 'your_facebook_app_secret');
define('SMO_TWITTER_API_KEY', 'your_twitter_api_key');
define('SMO_TWITTER_API_SECRET', 'your_twitter_api_secret');
define('SMO_LINKEDIN_CLIENT_ID', 'your_linkedin_client_id');
define('SMO_LINKEDIN_CLIENT_SECRET', 'your_linkedin_client_secret');
```

### Method 2: WordPress Admin

1. Navigate to **SMO Social > Settings > API Integrations**
2. Enter credentials for each platform
3. Click **Save Settings**
4. Test each connection using the **Test Connection** buttons

## 🧪 Testing Connections

After configuring credentials, test each connection:

1. Go to **SMO Social > Settings > API Integrations**
2. Click **Test Connection** next to each platform
3. Verify successful connection messages
4. Check for any error messages and resolve them

## 🔒 Security Best Practices

### API Key Security
- ✅ Never commit API keys to version control
- ✅ Use environment variables in production
- ✅ Regularly rotate API keys
- ✅ Monitor API usage for unusual activity
- ✅ Remove unused API keys promptly

For complete setup instructions, see the full documentation in the plugin admin area.