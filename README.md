<p align="center">
  <img src="https://img.shields.io/badge/Version-2.0.0-blue?style=for-the-badge" alt="Version">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/WordPress-5.0+-21759B?style=for-the-badge&logo=wordpress&logoColor=white" alt="WordPress">
  <img src="https://img.shields.io/badge/License-GPLv3-green?style=for-the-badge" alt="License">
</p>

<h1 align="center">🚀 SMO Social</h1>

<p align="center">
  <strong>A comprehensive WordPress plugin for social media optimization supporting 30+ platforms with AI-powered features, scheduling, analytics, real-time collaboration, and more.</strong>
</p>

<p align="center">
  <a href="https://github.com/serianis/SMO-Social/issues">Report Bug</a>
  ·
  <a href="https://github.com/serianis/SMO-Social/issues">Request Feature</a>
  ·
  <a href="https://texnologia.net">Website</a>
</p>

---

## 📋 Table of Contents

- [✨ Features](#-features)
- [🎯 Supported Platforms](#-supported-platforms)
- [📦 Installation](#-installation)
- [🚀 Quick Start](#-quick-start)
- [💡 Usage Examples](#-usage-examples)
- [🤖 AI Features](#-ai-features)
- [🔧 Configuration](#-configuration)
- [🛠️ Troubleshooting](#️-troubleshooting)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)
- [📞 Contact & Support](#-contact--support)

---

## ✨ Features

### 🌐 Multi-Platform Publishing
| Feature | Description |
|---------|-------------|
| **30+ Platforms** | Publish to Facebook, Twitter, Instagram, LinkedIn, TikTok, YouTube, and more |
| **Unified Dashboard** | Manage all platforms from a single interface |
| **Cross-Platform Sync** | Keep content synchronized across platforms |

### 🤖 AI-Powered Capabilities
- **Content Generation** - AI creates platform-optimized content
- **Hashtag Optimization** - Smart hashtag suggestions based on trending topics
- **Best Time Optimization** - AI analyzes your audience for optimal posting times
- **Content Variations** - Generate multiple versions for A/B testing
- **Trend Analysis** - Stay ahead with AI-powered trend detection

### 📊 Analytics & Insights
- Comprehensive performance tracking
- Audience demographics analysis
- Branded reports for clients
- Real-time engagement metrics

### 👥 Team Collaboration
- **Real-Time Editing** - Live collaboration on content
- **Approval Workflows** - Multi-step content review processes
- **Team Management** - Granular user roles and permissions
- **Internal Notes** - Team communication within posts

### 📅 Advanced Scheduling
- Visual calendar interface
- Queue management with retry mechanisms
- Time zone support
- Bulk scheduling operations

### 🔌 Cloud Integrations
- Google Drive
- Dropbox
- Canva
- Bulk content import (CSV, XML)

---

## 🎯 Supported Platforms

<table>
<tr>
<td>

**📱 Major Platforms**
- Facebook
- Twitter/X
- Instagram
- LinkedIn
- TikTok
- YouTube
- Snapchat

</td>
<td>

**💼 Professional Networks**
- LinkedIn Groups
- Reddit
- Medium
- Quora

</td>
<td>

**💬 Messaging**
- Telegram
- Discord
- WhatsApp Business
- Signal
- Line
- Viber

</td>
</tr>
<tr>
<td>

**🆕 Emerging Platforms**
- Threads
- BeReal
- Mastodon
- Bluesky

</td>
<td>

**🌍 Regional**
- VKontakte
- Weibo
- KakaoTalk
- Gab
- Parler

</td>
<td>

**🎨 Content Platforms**
- Pinterest
- Tumblr
- Flipboard
- Spotify
- Clubhouse

</td>
</tr>
</table>

---

## 📦 Installation

### Method 1: WordPress Plugin Directory (Recommended)

```bash
# Coming soon to WordPress.org
```

### Method 2: Manual Installation

1. **Download the plugin**
   ```bash
   git clone https://github.com/serianis/SMO-Social.git
   ```

2. **Upload to WordPress**
   ```bash
   # Copy to your WordPress plugins directory
   cp -r SMO-Social /path/to/wordpress/wp-content/plugins/
   ```

3. **Activate the plugin**
   - Go to `WordPress Admin → Plugins → Installed Plugins`
   - Find "SMO Social" and click **Activate**

### Method 3: ZIP Upload

1. Download the [latest release](https://github.com/serianis/SMO-Social/releases)
2. Go to `WordPress Admin → Plugins → Add New → Upload Plugin`
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4+ | 8.0+ |
| WordPress | 5.0+ | 6.4+ |
| MySQL | 5.6+ | 8.0+ |
| Memory | 128MB | 256MB+ |

---

## 🚀 Quick Start

### Step 1: Configure Your First Platform

```php
// Navigate to: SMO Social → Platforms → Add New
// Enter your API credentials for each platform
```

### Step 2: Create Your First Post

1. Go to `SMO Social → Create Post`
2. Write your content
3. Select target platforms
4. Choose posting time (now or scheduled)
5. Click **Publish**

### Step 3: Monitor Performance

Navigate to `SMO Social → Analytics` to view:
- Engagement metrics
- Audience growth
- Best performing content

---

## 💡 Usage Examples

### Creating a Multi-Platform Post

```php
// Example: Using the plugin programmatically
$post_data = array(
    'content' => 'Check out our latest product! 🚀',
    'platforms' => array('facebook', 'twitter', 'linkedin'),
    'schedule' => '2024-01-15 10:00:00',
    'hashtags' => array('#marketing', '#social'),
);

// The plugin handles platform-specific formatting automatically
```

### Using AI Content Generation

1. Navigate to `SMO Social → AI Assistant`
2. Enter your topic or keywords
3. Select target platforms
4. Click **Generate Content**
5. Review, edit, and publish

### Setting Up Approval Workflows

```
Admin → SMO Social → Team → Workflows
├── Create New Workflow
├── Define Approval Steps
│   ├── Step 1: Content Creator
│   ├── Step 2: Editor Review
│   └── Step 3: Manager Approval
└── Assign to Team Members
```

---

## 🤖 AI Features

| Feature | Description | Status |
|---------|-------------|--------|
| Content Generation | Generate platform-optimized posts | ✅ Active |
| Hashtag Suggestions | AI-powered trending hashtags | ✅ Active |
| Best Time Predictor | Optimal posting time analysis | ✅ Active |
| Sentiment Analysis | Analyze content tone | ✅ Active |
| Caption Generator | Auto-generate captions | ✅ Active |
| Alt Text Generator | Accessibility-focused image descriptions | ✅ Active |

---

## 🔧 Configuration

### API Credentials Setup

Each platform requires its own API credentials. Here's how to obtain them:

<details>
<summary><strong>Facebook/Instagram</strong></summary>

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app
3. Add Facebook Login and Instagram Basic Display
4. Copy App ID and App Secret to plugin settings

</details>

<details>
<summary><strong>Twitter/X</strong></summary>

1. Go to [Twitter Developer Portal](https://developer.twitter.com/)
2. Create a new project and app
3. Generate API Keys and Access Tokens
4. Copy credentials to plugin settings

</details>

<details>
<summary><strong>LinkedIn</strong></summary>

1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Create a new app
3. Request required permissions
4. Copy Client ID and Secret to plugin settings

</details>

### Environment Variables

```php
// Optional: wp-config.php configuration
define('SMO_SOCIAL_DEBUG', false);
define('SMO_SOCIAL_CACHE_DURATION', 3600);
```

---

## 🛠️ Troubleshooting

### Common Issues

<details>
<summary><strong>❌ Plugin activation fails</strong></summary>

**Solution:**
1. Ensure PHP 7.4+ is installed
2. Check WordPress is 5.0+
3. Verify write permissions on `wp-content/plugins/`
4. Check error logs: `wp-content/debug.log`

</details>

<details>
<summary><strong>❌ API connection errors</strong></summary>

**Solution:**
1. Verify API credentials are correct
2. Check if API rate limits are exceeded
3. Ensure SSL certificate is valid
4. Test with the built-in diagnostics: `SMO Social → Tools → Diagnostics`

</details>

<details>
<summary><strong>❌ Scheduled posts not publishing</strong></summary>

**Solution:**
1. Verify WordPress cron is working
2. Check server timezone settings
3. Ensure the plugin is properly activated
4. Review queue: `SMO Social → Posts → Queue`

</details>

### Debug Mode

Enable debug mode for detailed logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SMO_SOCIAL_DEBUG', true);
```

---

## 🤝 Contributing

We welcome contributions from the community! Here's how you can help:

### Getting Started

1. **Fork the repository**
   ```bash
   git clone https://github.com/serianis/SMO-Social.git
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```

3. **Make your changes**
   - Follow WordPress coding standards
   - Add tests for new functionality
   - Update documentation as needed

4. **Commit your changes**
   ```bash
   git commit -m "Add amazing feature"
   ```

5. **Push to your fork**
   ```bash
   git push origin feature/amazing-feature
   ```

6. **Open a Pull Request**

### Pull Request Guidelines

- ✅ Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- ✅ Include tests for new features
- ✅ Update documentation
- ✅ Keep commits atomic and well-described
- ✅ Reference related issues in PR description

### Code Style

```bash
# Run PHP CodeSniffer
composer install
./vendor/bin/phpcs --standard=WordPress ./includes/

# Run PHPStan for static analysis
./vendor/bin/phpstan analyse
```

### Reporting Bugs

Open an issue with:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior
- Error logs (if applicable)

---

## 📄 License

This project is licensed under the **GNU General Public License v3.0** (GPLv3).

```
SMO Social - Social Media Optimization Plugin
Copyright (C) 2024 Stelios Theodoridis

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
```

See the [LICENSE](LICENSE) file for full details.

---

## 📞 Contact & Support

<table>
<tr>
<td align="center">
<strong>👤 Author</strong><br>
Stelios Theodoridis
</td>
<td align="center">
<strong>🌐 Website</strong><br>
<a href="https://texnologia.net">texnologia.net</a>
</td>
<td align="center">
<strong>📧 Email</strong><br>
<a href="mailto:blogwalkingco@yahoo.gr">blogwalkingco@yahoo.gr</a>
</td>
</tr>
<tr>
<td align="center">
<strong>🐛 Issues</strong><br>
<a href="https://github.com/serianis/SMO-Social/issues">GitHub Issues</a>
</td>
<td align="center">
<strong>💬 Discussions</strong><br>
<a href="https://github.com/serianis/SMO-Social/discussions">GitHub Discussions</a>
</td>
<td align="center">
<strong>📦 Repository</strong><br>
<a href="https://github.com/serianis/SMO-Social">GitHub</a>
</td>
</tr>
</table>

---

<p align="center">
  <strong>⭐ If you find this plugin useful, please consider giving it a star on GitHub! ⭐</strong>
</p>

<p align="center">
  Made with ❤️ by <a href="https://texnologia.net">Stelios Theodoridis</a>
</p>

