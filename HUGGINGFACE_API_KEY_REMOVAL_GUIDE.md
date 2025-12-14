# HuggingFace API Key Removal Guide

## 🎯 Overview
This guide will help you completely remove your HuggingFace API key from both your local project and the HuggingFace website.

## ✅ Local Cleanup Completed

The following actions have been performed on your local project:

### Files Modified:
- ✅ `set_huggingface_key.php` - API key variable commented out
- ✅ `simple_hf_config.php` - All API key references removed
- ✅ `huggingface_setup_complete.php` - Display messages updated
- ✅ `.smo-social-config.php` - Already configured to use environment variables

### What was removed:
- Hardcoded API key: `hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
- All references to the specific API key in setup scripts
- Verification code that expected the old key

## 🌐 Important: Remove from HuggingFace Website

**Your API key is still active on HuggingFace!** You must remove it from the HuggingFace website to complete the security cleanup.

### Steps to remove from HuggingFace:

1. **Visit HuggingFace Settings**
   - Go to: https://huggingface.co/settings/tokens
   - Log in to your HuggingFace account

2. **Find Your API Key**
   - Look for the token that starts with: `hf_ttWdF...`
   - This matches your key: `hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

3. **Delete the Token**
   - Click the "Delete" button next to your API token
   - Confirm the deletion when prompted

4. **Verify Removal**
   - The token should disappear from your tokens list
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
$api_key = getenv('SMO_SOCIAL_HF_API_KEY') ?: '';
```

### Configuration File Security:
Your `.smo-social-config.php` is already configured properly:
```php
'smo_social_huggingface_api_key' => getenv('SMO_SOCIAL_HF_API_KEY') ?: '',
```

## 🚨 If You Need to Configure a New Key Later

If you want to set up a new HuggingFace API key in the future:

1. **Get a new API key from HuggingFace:**
   - Visit: https://huggingface.co/settings/tokens
   - Click "New token"
   - Give it a descriptive name
   - Copy the new token

2. **Set the environment variable:**
   ```bash
   export SMO_SOCIAL_HF_API_KEY="hf_your_new_token_here"
   ```

3. **Or set it in WordPress:**
   - Go to your WordPress admin panel
   - Navigate to SMO Social → Settings → AI Providers
   - Enter your new API key

## 📋 Verification Checklist

- [x] Hardcoded API key removed from project files
- [x] Configuration files updated to use environment variables
- [ ] API key removed from HuggingFace website (manual step required)
- [ ] Environment variables configured (if needed)
- [ ] AI functionality disabled until new key is configured

## 🆘 Troubleshooting

### If AI functionality is still working:
- Check if there are other API keys stored in WordPress options
- Clear any cached configurations
- Verify environment variables are set correctly

### If you can't access HuggingFace settings:
- Make sure you're logged into the correct HuggingFace account
- Try clearing your browser cache and cookies
- Contact HuggingFace support if needed

## ⚠️ Important Notes

- **Your API key may have usage costs associated with it** - monitor your account
- **The key may still be valid on HuggingFace** until you manually delete it
- **AI functionality will be disabled** until a new key is configured
- **Environment variables are the recommended approach** for production deployments

---

**Security Priority:** Complete the HuggingFace website removal as soon as possible to prevent unauthorized usage of your API key.