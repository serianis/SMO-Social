# SMO Social - WordPress Social Media Management Plugin

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)

SMO Social is a comprehensive WordPress plugin for managing social media content across multiple platforms. It provides AI-powered content generation, scheduling, analytics, and team collaboration features.

## 🚀 Features

### Multi-Platform Support
- **Facebook & Instagram** - Post scheduling, story management, and analytics
- **Twitter/X** - Tweet scheduling with thread support
- **LinkedIn** - Professional content distribution
- **YouTube** - Video post management and scheduling
- **Pinterest** - Pin scheduling and board management
- **TikTok** - Short-form video content management
- **Google Drive & Dropbox** - Cloud storage integration
- **Canva** - Design integration for visual content

### AI-Powered Features
- **Content Generation** - AI-powered post creation and optimization
- **Content Optimization** - Platform-specific content adaptation
- **Hashtag Suggestions** - Smart hashtag recommendations
- **Best Time Posting** - AI-driven scheduling optimization
- **Content Repurposing** - Automatically adapt content for different platforms

### Team Collaboration
- **User Roles & Permissions** - Granular access control
- **Approval Workflows** - Content approval system
- **Team Activity Tracking** - Monitor team member activities
- **Internal Notes** - Collaborative content planning

### Analytics & Reporting
- **Performance Analytics** - Comprehensive engagement metrics
- **Audience Demographics** - Detailed audience insights
- **Branded Reports** - Custom reporting templates
- **Real-time Dashboard** - Live performance monitoring

### Advanced Features
- **WebSocket Integration** - Real-time updates and notifications
- **Caching System** - Optimized performance with bounded cache
- **Security Features** - CSRF protection, input sanitization, audit logging
- **API Integration** - RESTful API for third-party integrations
- **Backup System** - Automated data backup and recovery

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- SSL certificate (required for social media APIs)

## 🔧 Installation

1. **Download the Plugin**
   ```bash
   # Clone the repository
   git clone https://github.com/your-username/smo-social.git
   ```

2. **Upload to WordPress**
   - Upload the `smo-social` folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins > Add New > Upload Plugin

3. **Activate the Plugin**
   - Go to WordPress Admin > Plugins
   - Activate "SMO Social"

4. **Configure API Credentials**
   - Go to SMO Social > Settings > API Integrations
   - Configure credentials for each social media platform
   - See the [API Setup Guide](docs/API_SETUP.md) for detailed instructions

## ⚙️ Configuration

### Environment Variables

For production deployments, use environment variables for sensitive data:

```bash
# Social Media API Keys
SMO_FACEBOOK_APP_ID=your_facebook_app_id
SMO_FACEBOOK_APP_SECRET=your_facebook_app_secret
SMO_TWITTER_API_KEY=your_twitter_api_key
SMO_TWITTER_API_SECRET=your_twitter_api_secret
SMO_LINKEDIN_CLIENT_ID=your_linkedin_client_id
SMO_LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret

# AI Service API Keys
SMO_HUGGINGFACE_API_KEY=your_huggingface_api_key
SMO_OPENROUTER_API_KEY=your_openrouter_api_key

# Cloud Storage
SMO_GOOGLE_CLIENT_ID=your_google_client_id
SMO_GOOGLE_CLIENT_SECRET=your_google_client_secret
SMO_DROPBOX_APP_KEY=your_dropbox_app_key
SMO_DROPBOX_APP_SECRET=your_dropbox_app_secret

# Plugin Settings
SMO_SOCIAL_ENV=production
SMO_SOCIAL_DEBUG=false
SMO_SOCIAL_LOG_LEVEL=error
```

### WordPress Configuration

Alternatively, configure via WordPress admin:

1. Navigate to **SMO Social > Settings**
2. Configure each platform's API credentials
3. Set up user permissions and roles
4. Configure AI providers
5. Set up integrations (Google Drive, Dropbox, Canva, etc.)

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

### Basic Post Scheduling
```php
// Schedule a post to multiple platforms
$smo_post = new SMO_Post();
$smo_post->set_content('Your post content here')
        ->add_media('path/to/image.jpg')
        ->set_platforms(['facebook', 'twitter', 'linkedin'])
        ->set_schedule_time('2024-01-15 14:30:00')
        ->save();
```

### AI Content Generation
```php
// Generate content using AI
$ai_manager = SMO_Social\AI\Manager::getInstance();
$content = $ai_manager->generate_content([
    'topic' => 'WordPress tips',
    'platform' => 'twitter',
    'tone' => 'professional',
    'length' => 'short'
]);
```

### Analytics Tracking
```php
// Get post performance metrics
$analytics = new SMO_Social\Analytics\Dashboard();
$metrics = $analytics->get_post_performance($post_id);
echo "Engagement Rate: " . $metrics['engagement_rate'] . "%";
```

## 🛠️ Development

### Local Development Setup
```bash
# Clone the repository
git clone https://github.com/your-username/smo-social.git
cd smo-social

# Install dependencies
composer install

# Run tests
composer test

# Build assets
npm install
npm run build
```

### Plugin Structure
```
smo-social/
├── smo-social.php          # Main plugin file
├── includes/               # Core functionality
│   ├── Admin/             # WordPress admin interface
│   ├── AI/                # AI-powered features
│   ├── Analytics/         # Analytics and reporting
│   ├── API/               # REST API endpoints
│   ├── Core/              # Core plugin functionality
│   ├── Platforms/         # Social media platform integrations
│   ├── Security/          # Security features
│   └── ...
├── assets/                # CSS, JavaScript, images
├── drivers/               # Platform configuration files
├── templates/             # Email and content templates
└── api/                   # API documentation
```

### Contributing
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

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

This plugin is licensed under the GPL v2 or later.

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
```

## 🙏 Acknowledgments

- WordPress community for the excellent platform
- Social media platforms for their APIs
- Open source contributors and testers
- Beta testers and feedback providers

---

**Made with ❤️ for the WordPress community**

For more information, visit [smosocial.com](https://smosocial.com)