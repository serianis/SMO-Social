<<<<<<< HEAD
# SMO Social - WordPress Social Media Management Plugin

[![Latest Release](https://img.shields.io/badge/release-v1.0.0-blue.svg)](https://github.com/serianis/SMO-Social/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)
[![GitHub Issues](https://img.shields.io/github/issues/serianis/SMO-Social.svg)](https://github.com/serianis/SMO-Social/issues)
[![GitHub Stars](https://img.shields.io/github/stars/serianis/SMO-Social.svg)](https://github.com/serianis/SMO-Social/stargazers)

SMO Social is a comprehensive WordPress plugin designed for professional social media management across multiple platforms. It offers AI-powered content generation, automated scheduling, advanced analytics, team collaboration tools, and robust security features to streamline social media workflows for businesses and content creators.

## Table of Contents

- [Features](#-features)
- [System Requirements](#-system-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage Examples](#-usage-examples)
- [Diagnostics and Troubleshooting](#-diagnostics-and-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)
- [Support](#-support)
- [Acknowledgments](#-acknowledgments)

## 🚀 Features

### Multi-Platform Social Media Management
- **Facebook & Instagram** - Advanced post scheduling, story management, and comprehensive analytics
- **Twitter/X** - Intelligent tweet scheduling with thread support and engagement tracking
- **LinkedIn** - Professional content distribution for personal and company pages
- **YouTube** - Complete video content management and automated scheduling
- **Pinterest** - Strategic pin scheduling and board organization
- **TikTok** - Short-form video content creation and management
- **Additional Platforms** - Support for Snapchat, WhatsApp Business, and more

### AI-Powered Content Tools
- **Smart Content Generation** - AI-driven post creation with platform-specific optimization
- **Content Repurposing** - Automatically adapt content for different social platforms
- **Hashtag Optimization** - Intelligent hashtag suggestions and trending analysis
- **Best Time Scheduling** - AI-powered posting time recommendations
- **Alt Text Generation** - Automated accessibility-compliant image descriptions

### Team Collaboration & Workflow
- **Role-Based Permissions** - Granular access control for team members
- **Content Approval System** - Multi-level approval workflows for quality control
- **Real-time Collaboration** - Live editing and commenting on content
- **Team Activity Monitoring** - Comprehensive tracking of team member actions
- **Internal Notes System** - Collaborative planning and content strategy

### Advanced Analytics & Reporting
- **Performance Metrics** - Detailed engagement, reach, and conversion analytics
- **Audience Demographics** - In-depth audience insights and segmentation
- **Branded Reports** - Customizable reporting templates for stakeholders
- **Real-time Dashboard** - Live performance monitoring and alerts
- **Competitive Analysis** - Benchmarking against industry standards

### Enterprise-Grade Features
- **WebSocket Integration** - Real-time updates and instant notifications
- **Bounded Caching System** - Optimized performance with intelligent memory management
- **Comprehensive Security** - CSRF protection, input sanitization, and audit logging
- **RESTful API** - Full API access for custom integrations
- **Automated Backup** - Scheduled data backup and disaster recovery
- **White-label Options** - Custom branding for agencies and enterprises

## 📋 System Requirements

### Minimum Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.6 or higher (8.0+ recommended)
- **Memory**: 256MB minimum, 512MB recommended
- **Disk Space**: 50MB for plugin files + space for media/cache

### Recommended Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 8.1 or higher
- **MySQL**: 8.0 or higher
- **Memory**: 512MB or higher
- **SSL Certificate**: Required for social media API integrations
- **Cron Jobs**: Enabled for automated scheduling

### Supported Browsers
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 🔧 Installation

### Method 1: Direct Download from GitHub

1. **Download the Plugin**
   ```bash
   # Clone the repository
   git clone https://github.com/serianis/SMO-Social.git
   cd SMO-Social
   ```

2. **Upload to WordPress**
   - Compress the entire folder into a ZIP file
   - Go to WordPress Admin → Plugins → Add New → Upload Plugin
   - Select the ZIP file and click "Install Now"

3. **Alternative Upload Method**
   - Upload the `SMO-Social` folder to `/wp-content/plugins/` via FTP/SFTP
   - Ensure proper file permissions (755 for directories, 644 for files)

### Method 2: Manual Plugin Activation

1. **Access WordPress Admin**
   - Navigate to **Plugins** in the WordPress dashboard

2. **Activate SMO Social**
   - Find "SMO Social" in the plugin list
   - Click **Activate**

3. **Initial Setup**
   - The plugin will create necessary database tables automatically
   - Visit **SMO Social → Settings** to begin configuration

### Post-Installation Configuration

1. **API Credentials Setup**
   - Navigate to **SMO Social → Settings → API Integrations**
   - Configure credentials for desired social platforms
   - Use the [API Setup Guide](docs/API_SETUP.md) for detailed instructions

2. **Environment Configuration**
   - Copy `.smo-social-config.php` to your WordPress root
   - Configure environment variables for production security

3. **Permissions Setup**
   - Configure user roles and permissions
   - Set up team member access levels

4. **Database Verification**
   - Run `setup-database.php` to ensure proper table creation
   - Verify database connection and permissions

## ⚙️ Configuration

### Environment Variables (Recommended for Production)

Create a secure configuration using environment variables:

```bash
# Core Plugin Configuration
SMO_SOCIAL_ENV=production
SMO_SOCIAL_DEBUG=false
SMO_SOCIAL_LOG_LEVEL=error
SMO_SOCIAL_ENCRYPTION_KEY=your_strong_encryption_key

# Social Media Platform API Keys
SMO_FACEBOOK_APP_ID=your_facebook_app_id
SMO_FACEBOOK_APP_SECRET=your_facebook_app_secret
SMO_INSTAGRAM_ACCESS_TOKEN=your_instagram_token
SMO_TWITTER_API_KEY=your_twitter_api_key
SMO_TWITTER_API_SECRET=your_twitter_api_secret
SMO_LINKEDIN_CLIENT_ID=your_linkedin_client_id
SMO_LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret
SMO_YOUTUBE_API_KEY=your_youtube_api_key
SMO_PINTEREST_ACCESS_TOKEN=your_pinterest_token
SMO_TIKTOK_ACCESS_TOKEN=your_tiktok_token

# AI Service API Keys
SMO_HUGGINGFACE_API_KEY=your_huggingface_api_key
SMO_OPENROUTER_API_KEY=your_openrouter_api_key

# Cloud Storage & Integration Services
SMO_GOOGLE_CLIENT_ID=your_google_client_id
SMO_GOOGLE_CLIENT_SECRET=your_google_client_secret
SMO_DROPBOX_APP_KEY=your_dropbox_app_key
SMO_DROPBOX_APP_SECRET=your_dropbox_app_secret
SMO_CANVA_API_KEY=your_canva_api_key
SMO_UNSPLASH_ACCESS_KEY=your_unsplash_key
SMO_PIXABAY_API_KEY=your_pixabay_key

# Automation & Webhook Services
SMO_ZAPIER_WEBHOOK_SECRET=your_zapier_secret
SMO_IFTTT_WEBHOOK_KEY=your_ifttt_key
SMO_WEBHOOK_SECRET=your_custom_webhook_secret
```

### WordPress Admin Configuration

For development or simpler setups, configure via WordPress admin:

1. **Access Plugin Settings**
   - Navigate to **SMO Social → Settings** in WordPress admin

2. **API Credentials Setup**
   - Go to **API Integrations** tab
   - Enter credentials for each platform you want to use
   - Use `api-credentials-setup.php` for batch configuration

3. **User Management**
   - Configure team member roles and permissions
   - Set up approval workflows for content publishing

4. **AI Provider Configuration**
   - Select preferred AI providers (HuggingFace, OpenRouter)
   - Configure API keys and usage limits

5. **Integration Setup**
   - Connect cloud storage services (Google Drive, Dropbox)
   - Configure design tools (Canva) and stock photo services

### Advanced Configuration Files

For complex deployments, use configuration files:

- **`.smo-social-config.php`** - Main configuration file
- **`api-credentials-setup.php`** - Batch API setup script
- **`setup-database.php`** - Database initialization
- **`production-security-config.php`** - Security hardening

## 📚 Documentation

- [API Setup Guide](docs/API_SETUP.md) - Detailed API configuration instructions
- [User Guide](docs/USER_GUIDE.md) - Complete user documentation
- [Developer Documentation](docs/DEVELOPER_GUIDE.md) - For developers extending the plugin
- [Troubleshooting](docs/TROUBLESHOOTING.md) - Common issues and solutions

## 🔌 Supported Platforms

### Social Media Platforms
- Facebook (Pages & Groups)
- Instagram
- Twitter/X
- LinkedIn (Personal & Company)
- YouTube
- Pinterest
- TikTok
- Snapchat
- WhatsApp Business

### Content Services
- Unsplash (Stock Photos)
- Pixabay (Stock Photos & Videos)
- Canva (Design Integration)
- Google Drive (File Storage)
- Dropbox (File Storage)
- OneDrive (File Storage)

### Automation Services
- Zapier (Workflow Automation)
- IFTTT (Simple Automations)
- Webhooks (Custom Integrations)

## 🎯 Usage Examples

### Plugin Activation and Initial Setup

1. **Manual Plugin Activation**
   ```bash
   # Run the activation script
   php activate_plugin.php
   ```

2. **Database Setup**
   ```bash
   # Initialize database tables
   php setup-database.php
   ```

3. **API Credentials Configuration**
   ```bash
   # Use batch configuration for multiple services
   php api-credentials-setup.php
   ```

### Content Creation and Scheduling

#### Basic Post Scheduling
```php
// Create and schedule a post to multiple platforms
$smo_post = new SMO_Post();
$smo_post->set_content('Exciting news: Our new WordPress plugin is now available! #WordPress #WebDev')
        ->add_media('/path/to/featured-image.jpg')
        ->set_platforms(['facebook', 'twitter', 'linkedin'])
        ->set_schedule_time('2024-01-15 14:30:00')
        ->add_hashtags(['WordPress', 'Plugin', 'WebDevelopment'])
        ->save();

echo "Post scheduled successfully for " . date('M j, Y g:i A', strtotime('2024-01-15 14:30:00'));
```

#### AI-Powered Content Generation
```php
// Generate content using AI providers
$ai_manager = SMO_Social\AI\Manager::getInstance();

// Generate a Twitter thread
$content = $ai_manager->generate_content([
    'topic' => 'WordPress security best practices',
    'platform' => 'twitter',
    'tone' => 'professional',
    'length' => 'thread',
    'audience' => 'developers'
]);

// Generate image alt text
$alt_text = $ai_manager->generate_alt_text('/path/to/image.jpg', 'WordPress plugin interface');

// Optimize hashtags
$hashtags = $ai_manager->optimize_hashtags('WordPress development tips', ['wordpress', 'php', 'webdev']);
```

#### Content Repurposing
```php
// Repurpose blog content for social media
$content_repurposer = new SMO_Social\AI\ContentRepurposer();

$blog_post = get_post(123); // WordPress post ID
$social_content = $content_repurposer->repurpose_content([
    'source_content' => $blog_post->post_content,
    'target_platform' => 'linkedin',
    'content_type' => 'article_summary',
    'max_length' => 280
]);
```

### Analytics and Reporting

#### Performance Tracking
```php
// Get comprehensive post performance metrics
$analytics = new SMO_Social\Analytics\Dashboard();
$metrics = $analytics->get_post_performance($post_id);

echo "Post Performance Report:\n";
echo "Engagement Rate: " . $metrics['engagement_rate'] . "%\n";
echo "Total Reach: " . number_format($metrics['reach']) . "\n";
echo "Click-through Rate: " . $metrics['ctr'] . "%\n";
echo "Best Performing Platform: " . $metrics['top_platform'] . "\n";
```

#### Audience Demographics
```php
// Analyze audience demographics
$audience_tracker = new SMO_Social\Analytics\AudienceDemographicsTracker();
$demographics = $audience_tracker->get_demographics($platform);

echo "Audience Demographics:\n";
echo "Age Groups: " . implode(', ', $demographics['age_groups']) . "\n";
echo "Gender Distribution: " . json_encode($demographics['gender']) . "\n";
echo "Geographic Distribution: " . json_encode($demographics['locations']) . "\n";
```

### Diagnostics and Testing

#### Run Diagnostic Tests
```bash
# Execute comprehensive diagnostics
php run_diagnostics.php

# Test AJAX security
php ajax_security_database_test.php

# Validate plugin implementation
php validate_implementation.php
```

#### Branding Diagnostics
```bash
# Check branding configuration
php branding_diagnostic.php

# Test menu registration
php menu-registration-test.php
```

#### Database Testing
```bash
# Test database connections
php test_database_connection_debug.php

# Validate schema
php database-schema-test-report.md
```

## 🔍 Diagnostics and Troubleshooting

### Built-in Diagnostic Tools

The plugin includes comprehensive diagnostic scripts for troubleshooting:

#### Core Diagnostics
```bash
# Run complete system diagnostics
php run_diagnostics.php

# Test AJAX security implementation
php ajax_security_database_test.php

# Validate overall implementation
php validate_implementation.php
```

#### Branding and UI Diagnostics
```bash
# Check branding configuration
php branding_diagnostic.php

# Test admin menu registration
php menu-registration-test.php

# Validate white-label settings
php validate-white-label-fix.php
```

#### Database and Performance Diagnostics
```bash
# Test database connections
php test_database_connection_debug.php

# Analyze database performance
php database-performance-analysis-report.md

# Test memory monitoring
php test_enhanced_memory_monitoring.php
```

#### API and Integration Testing
```bash
# Test AI provider connections
php simple_ai_test.php

# Validate API credentials
php credentials_validation_script.php

# Test webhook integrations
php step3_webhook_testing_demo.php
```

### Common Issues and Solutions

#### Plugin Activation Issues
- **Problem**: Plugin fails to activate
- **Solution**: Run `php troubleshoot_activation.php` and check file permissions
- **Prevention**: Ensure PHP 7.4+ and WordPress 5.0+

#### API Connection Problems
- **Problem**: Social platforms won't connect
- **Solution**: Use `api-credentials-setup.php` and verify SSL certificates
- **Check**: Run `php step1_standalone_config_report.php`

#### Performance Issues
- **Problem**: Slow loading or memory errors
- **Solution**: Run `php test_memory_monitoring_fix.php` and check cache settings
- **Optimization**: Use `performance-optimizations/` scripts

#### Database Errors
- **Problem**: Table creation or connection issues
- **Solution**: Execute `php setup-database.php` and verify MySQL permissions
- **Repair**: Use `php direct-database-fix.php` for schema fixes

### Debug Mode

Enable debug mode for detailed logging:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Plugin-specific debug
define('SMO_SOCIAL_DEBUG', true);
```

## 🤝 Contributing

We welcome contributions from the community! Here's how to get involved:

### Development Workflow

1. **Fork the Repository**
   ```bash
   git clone https://github.com/serianis/SMO-Social.git
   cd SMO-Social
   git checkout -b feature/your-feature-name
   ```

2. **Development Setup**
   ```bash
   # Install PHP dependencies
   composer install

   # Install Node.js dependencies (if applicable)
   npm install

   # Set up development environment
   cp .smo-social-config.php.example .smo-social-config.php
   ```

3. **Code Standards**
   - Follow WordPress Coding Standards
   - Use PHP 7.4+ syntax
   - Include comprehensive docblocks
   - Write unit tests for new features

4. **Testing**
   ```bash
   # Run PHP tests
   composer test

   # Run diagnostic scripts
   php run_diagnostics.php

   # Test plugin activation
   php test_plugin_activation.php
   ```

5. **Commit and Push**
   ```bash
   git add .
   git commit -m "feat: Add your feature description"
   git push origin feature/your-feature-name
   ```

6. **Create Pull Request**
   - Use clear, descriptive titles
   - Include detailed descriptions
   - Reference related issues
   - Ensure all tests pass

### Branching Strategy

- **`main`** - Production-ready code
- **`develop`** - Development integration branch
- **`feature/*`** - New features
- **`bugfix/*`** - Bug fixes
- **`hotfix/*`** - Critical production fixes

### Code Review Process

1. Automated checks (linting, tests)
2. Peer review by maintainers
3. Integration testing
4. Documentation updates
5. Release preparation

### Reporting Issues

- Use GitHub Issues for bug reports
- Include detailed reproduction steps
- Provide system information and error logs
- Suggest potential solutions when possible

### Feature Requests

- Check existing issues first
- Use the "Feature Request" template
- Include use cases and benefits
- Consider implementation complexity

## 🔒 Security

SMO Social implements comprehensive security measures:

- **API Key Encryption** - All sensitive credentials are encrypted
- **CSRF Protection** - Cross-site request forgery prevention
- **Input Sanitization** - All user inputs are sanitized
- **SQL Injection Prevention** - Prepared statements for all database queries
- **XSS Protection** - Output escaping and content filtering
- **Audit Logging** - Complete activity logging for security monitoring
- **Rate Limiting** - API rate limiting to prevent abuse

## 📊 Performance

The plugin is optimized for performance:

- **Bounded Cache** - Efficient memory usage with cache limits
- **Database Optimization** - Proper indexing and query optimization
- **Lazy Loading** - Content loaded on demand
- **Asset Minification** - Compressed CSS and JavaScript
- **CDN Ready** - Compatible with content delivery networks

## 🌟 Premium Features

The free version includes core functionality. Premium features available:

- Advanced analytics and reporting
- Team collaboration tools
- AI content generation credits
- Priority support
- Custom integrations
- White-label options

## 📞 Support

- **Documentation**: [docs/](docs/)
- **Support Forum**: [WordPress.org Support](https://wordpress.org/support/plugin/smo-social/)
- **GitHub Issues**: [Report bugs and request features](https://github.com/your-username/smo-social/issues)
- **Email Support**: support@smosocial.com (Premium users)

## 📄 License

SMO Social is licensed under the **GNU General Public License v2.0 or later** (GPL-2.0+).

```
SMO Social WordPress Plugin
Copyright (C) 2024 SMO Social Team

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
```

### License Compliance

- ✅ Compatible with WordPress GPL licensing
- ✅ Allows commercial use and modification
- ✅ Requires derivative works to remain GPL-licensed
- ✅ Provides source code access and modification rights

For the full license text, see [LICENSE](LICENSE) file.

## 🆘 Support

### Documentation and Resources

- 📚 **[Complete Documentation](docs/)** - Comprehensive user and developer guides
- 🔧 **[API Setup Guide](docs/API_SETUP.md)** - Detailed API configuration instructions
- 🐛 **[Troubleshooting Guide](docs/TROUBLESHOOTING.md)** - Common issues and solutions
- 📊 **[Performance Reports](database-performance-analysis-report.md)** - System performance analysis

### Community Support

- 💬 **GitHub Discussions** - Community forum for questions and discussions
- 🐛 **GitHub Issues** - Bug reports and feature requests
- 📧 **Email Support** - Premium support for licensed users
- 📖 **WordPress.org Forum** - General WordPress community support

### Getting Help

1. **Check Documentation First**
   - Review the [troubleshooting section](#-diagnostics-and-troubleshooting) above
   - Run diagnostic scripts: `php run_diagnostics.php`

2. **Search Existing Issues**
   - Check [GitHub Issues](https://github.com/serianis/SMO-Social/issues) for similar problems
   - Review closed issues for solutions

3. **Create Detailed Bug Reports**
   - Include WordPress version, PHP version, and error logs
   - Describe reproduction steps clearly
   - Provide system information from diagnostics

4. **Feature Requests**
   - Use the "Feature Request" issue template
   - Include use cases and implementation suggestions

### Professional Support

For enterprise deployments and priority support:
- Contact: support@smosocial.com
- Premium support packages available
- Custom development and integration services

## 🙏 Acknowledgments

- WordPress community for the excellent platform
- Social media platforms for their APIs
- Open source contributors and testers
- Beta testers and feedback providers
