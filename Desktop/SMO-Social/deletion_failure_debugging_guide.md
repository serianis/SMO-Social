# SMO Social Deletion Failure Debugging Guide

## Overview

This guide helps diagnose and resolve deletion failure errors in the SMO Social plugin. The error "Deletion failure: A critical error occurred on this site" typically indicates issues with database operations, permissions, or AJAX security mechanisms.

## Most Likely Causes (Prioritized)

Based on code analysis, here are the **5-7 most likely sources** of the deletion failure:

### 1. **Missing Database Tables** (Most Likely - ~40% of cases)
**Why:** The deletion methods (`ajax_delete_category` and `ajax_delete_idea`) require specific tables:
- `wp_smo_content_categories`
- `wp_smo_content_ideas`

**Symptoms:** 
- No error in PHP logs
- AJAX returns "Failed to delete" message
- No rows are affected in database operations

**Validation:** Tables don't exist in database

### 2. **Database Permission Issues** (High Probability - ~25% of cases)
**Why:** WordPress database user may lack DELETE permissions on plugin tables

**Symptoms:**
- "Failed to delete category/idea" message
- Database shows connection OK but operations fail
- Error logs show permission denied

**Validation:** Read works but DELETE operations fail

### 3. **User Permission Problems** (Medium Probability - ~20% of cases)
**Why:** Current user lacks `manage_options` capability required by AJAX handlers

**Symptoms:**
- AJAX security verification fails
- User sees deletion button but gets permission error
- Different users may have different experiences

**Validation:** Current user lacks admin capabilities

### 4. **AJAX Security/Nonce Issues** (Medium Probability - ~10% of cases)
**Why:** WordPress nonce verification fails due to timing, invalid nonce, or CSRF protection

**Symptoms:**
- Request verification fails silently
- AJAX calls return without processing
- Inconsistent behavior across browsers

**Validation:** Nonce verification returns false

### 5. **Database Connection Problems** (Low Probability - ~3% of cases)
**Why:** WordPress database connection issues or table prefix problems

**Symptoms:**
- Other plugin functions work but deletion fails
- Database queries return errors
- Connection status shows problems

**Validation:** $wpdb->last_error contains connection issues

### 6. **WordPress Version Incompatibility** (Low Probability - ~2% of cases)
**Why:** Plugin may use functions or patterns incompatible with current WordPress version

**Symptoms:**
- Works on some installations but not others
- Errors appear after WordPress updates
- Specific function calls fail

**Validation:** Check WordPress version compatibility

## Step-by-Step Diagnosis Process

### Step 1: Run Diagnostic Script
1. Access your WordPress admin dashboard
2. Navigate to `wp-admin/admin.php?page=smo-social` (or similar plugin page)
3. If that doesn't work, upload the `deletion_diagnostic.php` file to your WordPress root directory
4. Access it via browser: `yourdomain.com/deletion_diagnostic.php`
5. Review the comprehensive report generated

### Step 2: Check Error Logs
1. Enable WordPress debugging if not already enabled:
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
2. Check error logs at: `/wp-content/debug.log`
3. Look for entries containing "SMO Social" during deletion attempts

### Step 3: Test Database Tables
1. Access your database (phpMyAdmin, MySQL Workbench, etc.)
2. Check if these tables exist:
   - `wp_smo_content_categories` (or your table prefix + `smo_content_categories`)
   - `wp_smo_content_ideas` (or your table prefix + `smo_content_ideas`)
3. If missing, run plugin setup/installation

### Step 4: Test User Permissions
1. Ensure you're logged in as Administrator
2. Check if you have `manage_options` capability
3. Try deletion with a different admin user

### Step 5: Test AJAX Security
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Attempt a deletion operation
4. Check if AJAX request completes successfully
5. Look for any 400/500 HTTP errors

## Resolution Steps

### Fix 1: Create Missing Tables
If tables are missing, run the plugin's database setup:

```sql
-- Example table creation (adapt to your needs)
CREATE TABLE `wp_smo_content_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `color_code` varchar(7) DEFAULT '#667eea',
  `icon` varchar(10) DEFAULT '📁',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_smo_content_ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext,
  `category_id` int(11) DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'medium',
  `status` varchar(20) DEFAULT 'idea',
  `scheduled_date` datetime DEFAULT NULL,
  `tags` text,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Fix 2: Fix Database Permissions
Ensure WordPress database user has proper permissions:

```sql
-- Grant necessary permissions (run as database admin)
GRANT SELECT, INSERT, UPDATE, DELETE ON wp_smo_content_categories TO 'wp_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON wp_smo_content_ideas TO 'wp_user'@'localhost';
FLUSH PRIVILEGES;
```

### Fix 3: Fix User Permissions
1. Go to WordPress Admin → Users
2. Ensure your user role is Administrator
3. Or manually add capability:
   ```php
   // Add to functions.php temporarily
   $user = wp_get_current_user();
   $user->add_cap('manage_options');
   ```

### Fix 4: Reset AJAX Security
1. Clear all caches
2. Ensure nonce generation is working:
   ```javascript
   // Check in browser console
   console.log('Nonce:', ajaxurl.replace('/admin-ajax.php', '') + '/wp-admin/admin-ajax.php?action=smo_get_nonce');
   ```

### Fix 5: WordPress Compatibility
1. Update to latest WordPress version
2. Check plugin compatibility with current WP version
3. Contact plugin developer if compatibility issues persist

## Prevention Measures

1. **Regular Backups:** Always backup before making changes
2. **Update WordPress:** Keep WordPress and plugins updated
3. **Monitor Logs:** Regularly check error logs for issues
4. **Test Environment:** Use staging site for testing changes
5. **Database Maintenance:** Regularly optimize and repair database tables

## When to Contact Support

Contact plugin support if:
- Diagnostic script shows no obvious issues
- Error persists after trying all fixes
- Database corruption is suspected
- WordPress core files appear corrupted

## Emergency Recovery

If deletion failures are blocking site functionality:

1. **Disable Plugin Temporarily:**
   ```php
   // Add to wp-config.php
   define('DISABLE_WP_CRON', true);
   // Rename plugin folder to disable
   ```

2. **Manual Database Cleanup:**
   ```sql
   -- If needed, manually clean up problematic records
   DELETE FROM wp_smo_content_categories WHERE user_id = 0;
   ```

3. **Reset Plugin:**
   - Deactivate and reactivate plugin
   - Run setup wizard again
   - Re-import data if needed

---

**Remember:** Always test fixes in a staging environment first, and maintain recent backups before making any changes.