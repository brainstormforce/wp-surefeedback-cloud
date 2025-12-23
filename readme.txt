=== SureFeedback Cloud ===
Contributors: brainstormforce
Donate link: https://surefeedback.com
Tags: surefeedback, client, feedback, design
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 0.0.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a secure connection between your SureFeedback parent and client sites, syncing identities for WordPress-based commenting.

== Description ==

This is the Client plugin for [SureFeedback](https://surefeedback.com)

The SureFeedback plugin lets you collect sticky note-style feedback on page designs and web projects. It’s so easy to use. Clients can select specific areas of your design, point, click, and type constructive comments on top of your mockups and site designs.

Using SureFeedback, the client can show as well as tell, providing targeted feedback for a more efficient workflow. 

SureFeedback is a self-hosted client feedback system that allows you to get feedback on an endless amount of client sites from one central dashboard.

The [SureFeedback](https://surefeedback.com) plugin is used to securely sync multiple WordPress client identities with your SureFeedback parent site.

All you need to do is install the plugin on the site you want feedback on and it's ready to go.

**Features include:**

* Connect and sync your client’s identities with your SureFeedback projects.
* No login or registration is required if your client is logged into their own site.
* Choose which roles you want to allow for commenting.
* Allow non-users (guests) to leave comments.
* Optionally enable commenting on the WordPress admin.
* White label support

== External Services ==

This plugin connects to the SureFeedback Cloud API (https://api.surefeedback.com) to provide visual feedback and collaboration features. This external service is required for the plugin to function.

**What is the service?**

SureFeedback Cloud is a visual feedback and collaboration platform that allows you to collect client feedback directly on your website designs and pages. The API service handles:
- User authentication and authorization
- Feedback data synchronization
- Real-time collaboration features
- Comment and annotation storage

**What data is sent and when?**

The plugin communicates with api.surefeedback.com in the following scenarios:

1. **During Connection Setup**: When you connect your WordPress site to SureFeedback Cloud, the plugin sends:
   - Your site URL
   - WordPress REST API endpoint URL
   - Connection verification tokens

2. **During User Authentication**: When users access the feedback widget, the plugin may send:
   - User role information (if logged in)
   - Session tokens for authentication
   - Page identification data (URL, page ID, page type)

3. **During Feedback Collection**: When feedback is being collected, the following data is transmitted:
   - Page screenshots and visual data
   - Comment content and metadata
   - User identification (name, email if provided)
   - Element positioning data for visual markers

4. **During Synchronization**: The plugin periodically syncs:
   - Connection health status
   - Configuration updates
   - Widget visibility settings

**Data Privacy and Security**

All data transmission occurs over HTTPS encrypted connections. The plugin uses secure token-based authentication and follows WordPress security best practices.

**Service Terms and Privacy**

By using this plugin, you agree to the SureFeedback Cloud service terms:
- Terms of Service: https://surefeedback.com/terms-of-service/
- Privacy Policy: https://surefeedback.com/privacy-policy/

Please review these documents to understand how your data is handled by the SureFeedback Cloud service.

== Developer Resources ==

This plugin contains minified JavaScript and CSS files for optimal performance. The complete, non-minified source code is included in the plugin and can be found in the following locations:

**Source Code Locations:**
- Vue.js/TypeScript source files: `/src/` directory
- Build configuration: `webpack.config.js`, `postcss.config.cjs`, `tailwind.config.ts`
- PHP source files: Root directory and `/includes/` directory

**Build Tools & Requirements:**

To build the plugin from source, you'll need:
- Node.js 18+ and npm/pnpm
- PHP 7.4+
- Composer (for PHP dependencies)

**Building from Source:**

```bash
# Install Node.js dependencies
npm install

# Install PHP dependencies
composer install

# Build Vue.js assets (development)
npm run dev

# Build Vue.js assets (production)
npm run build

# Build everything (Vue.js + translations)
grunt build

# Create distribution package
grunt release
```

**Development Commands:**

```bash
# PHP Code Standards
composer lint          # Check PHP code standards
composer format        # Auto-fix PHP code standards
composer phpstan       # Run static analysis

# JavaScript/Vue.js Development
npm run dev            # Development server with hot reload
npm run build          # Production build
npm run type-check     # TypeScript type checking

# Translation Files
grunt i18n             # Generate translation files
```

**Compiled Assets:**

The following files are compiled from source and included in the distribution:
- `assets/js/admin.js` - Compiled from Vue.js/TypeScript sources in `/src/`
- `assets/dist/admin.css` - Compiled from Tailwind CSS and PostCSS
- `languages/surefeedback-cloud.pot` - Generated from source files

All source code is included in the plugin package, ensuring full transparency and compliance with open source requirements.

== Installation ==

1. Go to `Plugins -> Add New` and search for SureFeedback
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to `Settings -> SureFeedback` to configure the plugin options.

== Frequently Asked Questions ==

= What is the purpose of this plugin? =

The purpose of this plugin is to make it simple to get targeted feedback from clients on web designs. All you have to do is install the [SureFeedback](https://surefeedback.com) plugin and let your clients select areas of your design to add their own comments. Everything is tracked within the plugin. It's so easy to use!

= How do I connect my site to SureFeedback? =

After installing the plugin, go to `Settings -> SureFeedback` and enter your connection details. The plugin will securely verify your connection with the SureFeedback parent site.

= Can I control which pages show the feedback widget? =

Yes! The Widget Control feature allows you to enable or disable the feedback widget on a per-page basis. You can use the search and filter options to quickly manage multiple pages.

= Who can leave feedback? =

You can control who can leave feedback through the Permissions settings. You can allow specific user roles, site visitors, or even guests to leave comments. You can also enable commenting on the WordPress admin dashboard.

= What are the system requirements? =

This plugin requires WordPress 4.7 or higher and PHP 7.4 or higher.

== Screenshots ==

1. Connection settings page
2. Widget control interface
3. Permission management panel
4. General settings configuration

== Changelog ==

= 0.0.1 =
* Initial release
* Connections - Secure connection to SureFeedback Cloud platform with verification and status management
* Widget Control - Per-page widget visibility control with search, filter, and bulk enable/disable options
* Permission Management - User role permissions, site visitor access, dashboard commenting, and guest access control
* Modern React-based admin interface
* Secure API integration with SureFeedback platform
* Comprehensive settings and configuration panel
* White label customization support
* Role-based access control
* Guest commenting capabilities
* Built-in troubleshooting tools
* PHP 7.4+ compatibility
