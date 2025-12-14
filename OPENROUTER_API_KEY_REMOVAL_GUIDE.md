# OpenRouter API Key Removal Guide

## 🎯 Overview
This guide will help you completely remove your OpenRouter API key from both your local project and the OpenRouter website.

## ✅ Local Cleanup Completed

The following actions have been performed on your local project:

### Files Modified:
- ✅ `tests/test-openrouter.php` - OpenRouter API key removed and empty key handling added
- ✅ WordPress options check (if available) - Searches for stored API keys

### API Key Removed:
```
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
```

### What was changed:
- Hardcoded API key replaced with placeholder `[REMOVED_OPENROUTER_API_KEY]`
- Empty API key handling added to test file
- WordPress options checked for stored keys
- Test file will show error if run without configured key

## 🌐 Important: Remove from OpenRouter Website

**Your API key is still active on OpenRouter!** You must remove it from the OpenRouter website to complete the security cleanup.

### Steps to remove from OpenRouter:

1. **Visit OpenRouter Keys Page**
   - Go to: https://openrouter.ai/keys
   - Log in to your OpenRouter account

2. **Find Your API Key**
   - Look for the key that starts with: `sk-or-v1-a8851af...`
   - This matches your key: `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`

3. **Delete the Key**
   - Click the "Delete" or "Remove" button next to your API key
   - Confirm the deletion when prompted

4. **Verify Removal**
   - The key should disappear from your keys list
   - You should see a confirmation message

## 🔒 Security Best Practices

### For Future API Key Management:
- ✅ Use environment variables instead of hardcoded keys
- ✅ Never commit API keys to version control
- ✅ Regularly rotate your API keys
- ✅ Monitor API usage for unusual activity
- ✅ Remove unused API keys promptly

### Environment Variable Setup:
Instead of hardcoded keys, use environment variables:
```php
// Good practice
$api_key = getenv('SMO_SOCIAL_OPENROUTER_API_KEY') ?: '';
```

### WordPress Integration:
The plugin is configured to use WordPress options:
- Option name: `smo_social_openrouter_api_key`
- Access via: WordPress Admin → SMO Social → Settings → AI Providers

## 🧪 Testing and Configuration

### Test File Status:
- `tests/test-openrouter.php` has been updated
- Will show error if run without configured API key
- To test again: Set your new API key in the file

### To Configure a New Key Later:

1. **Get a new API key from OpenRouter:**
   - Visit: https://openrouter.ai/keys
   - Click "Create Key"
   - Copy the new key

2. **For Testing:**
   ```php
   // In tests/test-openrouter.php
   $api_key = 'sk-or-v1-your-new-key-here';
   ```

3. **For WordPress:**
   - Go to WordPress Admin → SMO Social → Settings → AI Providers
   - Select "OpenRouter" as provider
   - Enter your new API key

## 📋 Verification Checklist

- [x] Hardcoded API key removed from test file
- [x] Empty key handling implemented
- [ ] API key removed from OpenRouter website (manual step required)
- [ ] Environment variables configured (if needed)
- [ ] AI functionality disabled until new key is configured

## 🚨 If You Need to Test OpenRouter Integration

### Test File Usage:
```bash
# Run the test (will show error without key)
php tests/test-openrouter.php

# To test with a new key, edit the file:
# tests/test-openrouter.php
$api_key = 'sk-or-v1-your-new-key-here';
```

### Available Models for Testing:
- `kwaipilot/kat-coder-pro:free` (free model)
- `openai/gpt-4` (premium model)
- `anthropic/claude-3-opus` (premium model)

## 🆘 Troubleshooting

### If you can't access OpenRouter keys:
- Make sure you're logged into the correct OpenRouter account
- Try clearing your browser cache and cookies
- Contact OpenRouter support if needed

### If API functionality is still working:
- Check if there are other API keys stored in WordPress options
- Clear any cached configurations
- Verify the key was actually removed from OpenRouter

## ⚠️ Important Notes

- **Your API key may have usage costs associated with it** - monitor your account
- **The key may still be valid on OpenRouter** until you manually delete it
- **OpenRouter functionality will be disabled** until a new key is configured
- **OpenRouter provides access to 100+ AI models** - check pricing at https://openrouter.ai/pricing

## 🔍 Key Information

**Your removed API key:** `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`
**Key format:** Starts with `sk-or-v1-` followed by 64 alphanumeric characters
**Provider:** OpenRouter (AI model router)
**Website:** https://openrouter.ai/

---

**Security Priority:** Complete the OpenRouter website removal as soon as possible to prevent unauthorized usage of your API key.