# Claude Code Master Prompt — WordPress Social Publisher Plugin
# Copy this entire prompt and paste it into Claude Code to begin

---

## MISSION

You are an expert WordPress plugin developer. Your job is to build, test, and package
a production-ready WordPress plugin called **WP Social Publisher** that allows WordPress
users to cross-post to Facebook, Instagram, LinkedIn, and X (Twitter) directly from the
WordPress post editor. The plugin must be fully self-contained, installable as a .zip,
and safe to activate on a live WordPress site.

Work autonomously through all phases: scaffold → build → test → validate → package.
Do not stop to ask questions unless you hit a genuine blocker. Make sensible defaults
and document every assumption you make in a DECISIONS.md file.

---

## PHASE 1 — PROJECT SCAFFOLD

Create the following directory structure inside a folder named `wp-social-publisher`:

```
wp-social-publisher/
├── wp-social-publisher.php          # Main plugin bootstrap file
├── uninstall.php                    # Cleanup on plugin deletion
├── readme.txt                       # WordPress.org format readme
├── DECISIONS.md                     # Document every assumption made
├── includes/
│   ├── class-wsp-activator.php      # Activation: create DB tables, set defaults
│   ├── class-wsp-deactivator.php    # Deactivation: flush cron, clear transients
│   ├── class-wsp-loader.php         # Hook registration manager
│   ├── class-wsp-meta-box.php       # Classic editor meta box
│   ├── class-wsp-gutenberg.php      # Gutenberg sidebar panel (REST + JS)
│   ├── class-wsp-publisher.php      # Core dispatch orchestrator
│   ├── class-wsp-facebook.php       # Facebook Graph API handler
│   ├── class-wsp-instagram.php      # Instagram Graph API handler
│   ├── class-wsp-linkedin.php       # LinkedIn REST API v2 handler
│   ├── class-wsp-twitter.php        # X (Twitter) API v2 handler
│   ├── class-wsp-token-manager.php  # Token storage, refresh, expiry alerts
│   ├── class-wsp-post-log.php       # Activity log (custom DB table)
│   └── class-wsp-helpers.php        # Shared utility functions
├── admin/
│   ├── class-wsp-admin.php          # Admin menu, enqueue scripts
│   ├── settings-page.php            # API credentials settings UI
│   ├── log-viewer.php               # Activity log dashboard page
│   └── partials/
│       ├── settings-facebook.php    # Facebook/Instagram settings section
│       ├── settings-linkedin.php    # LinkedIn settings section
│       └── settings-twitter.php     # X settings section
├── assets/
│   ├── src/
│   │   └── sidebar.js               # Gutenberg sidebar React component (source)
│   ├── build/
│   │   └── sidebar.js               # Compiled JS (you will generate this manually)
│   ├── admin.css                     # Admin styles
│   └── admin.js                      # Classic editor JS
├── languages/
│   └── wp-social-publisher.pot      # Translation template
└── tests/
    ├── bootstrap.php                # PHPUnit bootstrap
    ├── test-publisher.php           # Core publisher unit tests
    ├── test-facebook.php            # Facebook API class tests
    ├── test-token-manager.php       # Token manager tests
    └── test-helpers.php             # Helper function tests
```

---

## PHASE 2 — CORE PLUGIN FILE

### wp-social-publisher.php

Write the main plugin bootstrap file. It must include:

```php
/**
 * Plugin Name:       WP Social Publisher
 * Plugin URI:        https://yoursite.com/wp-social-publisher
 * Description:       Cross-post WordPress posts to Facebook, Instagram, LinkedIn, and X from the editor.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * Text Domain:       wp-social-publisher
 * Domain Path:       /languages
 */
```

Requirements:
- Define constants: `WSP_VERSION`, `WSP_PLUGIN_DIR`, `WSP_PLUGIN_URL`, `WSP_PLUGIN_FILE`
- Abort with `die()` if accessed directly (not via WordPress)
- Register activation hook → `WSP_Activator::activate()`
- Register deactivation hook → `WSP_Deactivator::deactivate()`
- Register uninstall hook → `uninstall.php`
- Autoload all classes from `includes/` and `admin/`
- Initialize the `WSP_Loader` which registers all hooks

---

## PHASE 3 — DATABASE & ACTIVATION

### class-wsp-activator.php

On activation, create a custom table `{prefix}wsp_post_log` using `dbDelta()`:

```sql
CREATE TABLE {prefix}wsp_post_log (
  id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id     BIGINT(20) UNSIGNED NOT NULL,
  platform    VARCHAR(20) NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  social_id   VARCHAR(255) DEFAULT NULL,
  error_msg   TEXT DEFAULT NULL,
  caption     TEXT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY post_id (post_id),
  KEY platform (platform),
  KEY status (status)
);
```

Also:
- Store plugin version in `wsp_version` option
- Set default options for `wsp_settings` (empty credentials, global defaults)
- Schedule a daily cron event `wsp_token_check` to verify token health

---

## PHASE 4 — PUBLISHER CORE

### class-wsp-publisher.php

This is the heart of the plugin. Requirements:

1. Hook into `transition_post_status` — fire only when transitioning TO `publish`
2. Skip auto-saves, revisions, and post updates to already-published posts
3. Read post meta `_wsp_channels` (array of selected platforms for this post)
4. Read post meta `_wsp_captions` (array of custom captions per platform)
5. For each checked platform, schedule an async cron event using `wp_schedule_single_event()`
   with a 5-second delay — this prevents the publish action from timing out
6. Each cron event calls the corresponding API class
7. On completion, write result to the log table via `WSP_Post_Log`
8. Handle the case where a post is re-published (update, not duplicate)

```php
// Cron hook naming convention:
// wsp_publish_facebook_{post_id}
// wsp_publish_instagram_{post_id}
// wsp_publish_linkedin_{post_id}
// wsp_publish_twitter_{post_id}
```

---

## PHASE 5 — PLATFORM API CLASSES

### class-wsp-facebook.php

Implement `WSP_Facebook::publish( $post_id, $caption )`:

- Retrieve Page Access Token from `wsp_settings` option (encrypted)
- Retrieve Page ID from settings
- Build Graph API v18.0 endpoint: `https://graph.facebook.com/v18.0/{PAGE_ID}/feed`
- POST payload: `{ message: caption, link: post_permalink }`
- If post has a featured image: use `/photos` endpoint instead with `url` parameter
- Return: `[ 'success' => bool, 'social_id' => string|null, 'error' => string|null ]`
- Log full API response on failure using `error_log()`
- Handle HTTP errors (401 token expired, 403 permissions, 400 bad request) distinctly

---

### class-wsp-instagram.php

Implement `WSP_Instagram::publish( $post_id, $caption )`:

- Instagram Graph API requires 2 steps: create container → publish container
- Step 1: POST to `/{IG_USER_ID}/media` with `{ image_url, caption }`
- Step 2: POST to `/{IG_USER_ID}/media_publish` with `{ creation_id }`
- If no featured image: log a warning and skip gracefully (DO NOT fail silently — write
  a log entry with status `skipped` and message "No featured image found")
- The image must be publicly accessible — use `wp_get_attachment_image_src()` with
  `full` size and verify the URL is not a local/dev URL
- Return same structure as Facebook class

---

### class-wsp-linkedin.php

Implement `WSP_LinkedIn::publish( $post_id, $caption )`:

- Use LinkedIn UGC Posts API: `https://api.linkedin.com/v2/ugcPosts`
- Auth: Bearer token from settings
- Payload structure:
```json
{
  "author": "urn:li:person:{PERSON_ID}",
  "lifecycleState": "PUBLISHED",
  "specificContent": {
    "com.linkedin.ugc.ShareContent": {
      "shareCommentary": { "text": "{caption}" },
      "shareMediaCategory": "ARTICLE",
      "media": [{
        "status": "READY",
        "originalUrl": "{post_url}"
      }]
    }
  },
  "visibility": {
    "com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"
  }
}
```
- Support both Person URN and Organization URN (toggle in settings)
- Return same structure as other classes

---

### class-wsp-twitter.php

Implement `WSP_Twitter::publish( $post_id, $caption )`:

- Use X API v2 endpoint: `https://api.twitter.com/2/tweets`
- Auth: OAuth 1.0a using Consumer Key, Consumer Secret, Access Token, Access Token Secret
- Implement OAuth 1.0a signature generation from scratch (do not rely on external library)
  using: HMAC-SHA1, percent-encoding, nonce generation, timestamp
- Truncate caption to 280 characters, appending post URL if it fits
- If featured image exists: first upload via v1.1 media endpoint, then attach `media_ids`
- Return same structure as other classes

---

### class-wsp-token-manager.php

Requirements:
- `encrypt( $value )` and `decrypt( $value )` using `openssl_encrypt` with AES-256-CBC
  and a site-specific key derived from `AUTH_KEY` WordPress constant
- `get_token( $platform )` — retrieve and decrypt stored token
- `save_token( $platform, $token )` — encrypt and store token
- `check_expiry( $platform )` — return days until expiry (read from stored expiry timestamp)
- `send_expiry_notice( $platform, $days_remaining )` — send WP admin email notice
  when token expires in ≤ 7 days
- Called daily by `wsp_token_check` cron event

---

## PHASE 6 — EDITOR UI

### class-wsp-meta-box.php (Classic Editor)

- Register meta box on `add_meta_boxes` hook for all public post types
- Meta box title: "Publish to Social Media"
- Render checkboxes for: Facebook, Instagram, LinkedIn, X
- Below each checked platform: textarea for optional custom caption
- Show platform icon/colour using CSS (no external images needed — use Unicode or CSS)
- Add nonce field for security
- On `save_post`: verify nonce, check capabilities, sanitize and save meta
  - `_wsp_channels` → `sanitize_text_field` array
  - `_wsp_captions` → `wp_kses_post` array

---

### Gutenberg Sidebar (assets/src/sidebar.js)

Since we cannot run `@wordpress/scripts` build in this environment, write the sidebar
as a **standalone compiled-compatible script** that uses `wp.plugins`, `wp.editPost`,
`wp.components`, and `wp.data` globals already loaded by WordPress.

```javascript
// Use WordPress global wp object — no import statements
const { registerPlugin } = wp.plugins;
const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
const { PanelBody, CheckboxControl, TextareaControl, Notice } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { useState, useEffect } = wp.element;
```

Features:
- Sidebar panel titled "Social Media" with a share icon
- Checkboxes for each platform with their brand colour indicator
- Conditional textarea per platform (appears when checkbox is checked)
- Live character counter (red when over platform limit)
- "Post has already been published to [Platform]" notice if post was previously sent
- Save selections to post meta via `editPost` dispatch

Write this as a clean ES5-compatible IIFE that works without a build step.
Save as `assets/build/sidebar.js` (the "built" version that WP will enqueue).

---

## PHASE 7 — ADMIN SETTINGS PAGE

### admin/settings-page.php

Build a tabbed settings page registered under WordPress Settings API:

**Tab 1 — Facebook / Instagram**
- Facebook App ID (text)
- Facebook App Secret (password — shown masked)
- Page Access Token (password)
- Facebook Page ID (text)
- Instagram User ID (text)
- "Test Connection" button per platform (AJAX call that does a test API ping)

**Tab 2 — LinkedIn**
- Client ID (text)
- Client Secret (password)
- Access Token (password)
- Person/Organization URN (text)
- Token expiry date (read-only, populated from saved data)

**Tab 3 — X (Twitter)**
- Consumer Key (text)
- Consumer Secret (password)
- Access Token (text)
- Access Token Secret (password)
- "Test Connection" button

**Tab 4 — Defaults**
- Default hashtags per platform (text, comma-separated)
- Auto-append post URL toggle (checkbox)
- Post types to enable social panel for (checkboxes for each registered post type)
- Log retention days (number, default 90)

Security requirements:
- All credential fields use `sanitize_text_field` on save
- Tokens stored via `WSP_Token_Manager::save_token()` (encrypted)
- Settings page only visible to `manage_options` capability
- Add CSRF nonce to all AJAX endpoints

---

## PHASE 8 — ACTIVITY LOG

### admin/log-viewer.php + class-wsp-post-log.php

Build a `WP_List_Table` subclass for the activity log with these columns:

| Column       | Content                                      |
|--------------|----------------------------------------------|
| Post         | Post title (linked to edit screen)           |
| Platform     | Platform name with coloured badge            |
| Status       | sent / failed / skipped / pending (coloured) |
| Caption      | Truncated caption (hover to see full)        |
| Social ID    | ID returned by platform API (if success)     |
| Date         | Formatted datetime                           |
| Actions      | Retry button (for failed), View Post link    |

Features:
- Filter by platform (dropdown)
- Filter by status (dropdown)
- Date range filter
- Bulk delete
- Auto-purge entries older than configured retention period (via cron)
- "Retry" action triggers a new async cron publish attempt

---

## PHASE 9 — TESTING

### tests/ directory

Write PHPUnit tests for all critical paths. Use WordPress test suite conventions.

#### tests/test-publisher.php

```php
class Test_WSP_Publisher extends WP_UnitTestCase {

    // Test: publish fires on transition to 'publish' status
    public function test_publish_fires_on_status_transition() { ... }

    // Test: publish does NOT fire on auto-save
    public function test_no_publish_on_autosave() { ... }

    // Test: publish does NOT fire on revision
    public function test_no_publish_on_revision() { ... }

    // Test: correct platforms dispatched based on post meta
    public function test_correct_platforms_dispatched() { ... }

    // Test: cron events scheduled with correct args
    public function test_cron_events_scheduled() { ... }

    // Test: re-publish does not duplicate social posts
    public function test_no_duplicate_on_republish() { ... }
}
```

#### tests/test-token-manager.php

```php
class Test_WSP_Token_Manager extends WP_UnitTestCase {

    // Test: token encrypts and decrypts correctly
    public function test_encrypt_decrypt_roundtrip() { ... }

    // Test: expiry check returns correct days remaining
    public function test_expiry_check_days_remaining() { ... }

    // Test: expiry notice sent when 7 days or fewer remain
    public function test_expiry_notice_sent_at_threshold() { ... }

    // Test: get_token returns null for missing platform
    public function test_get_token_returns_null_for_missing() { ... }
}
```

#### tests/test-helpers.php

Test all functions in `class-wsp-helpers.php`:
- Caption truncation respects byte limits
- URL appending logic
- Hashtag normalisation
- Featured image URL retrieval

#### tests/bootstrap.php

```php
<?php
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    require dirname( dirname( __FILE__ ) ) . '/wp-social-publisher.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
```

Also write a `phpunit.xml` configuration file at the plugin root.

---

## PHASE 10 — CODE QUALITY & SECURITY AUDIT

Before packaging, perform a self-audit of the entire codebase and fix any issues found:

### Security Checklist — verify every item:

- [ ] All `$_POST` / `$_GET` / `$_REQUEST` values are sanitized before use
- [ ] All database queries use `$wpdb->prepare()` — zero raw SQL with variables
- [ ] All output to HTML is escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- [ ] All AJAX handlers verify nonce with `check_ajax_referer()`
- [ ] All AJAX handlers verify user capability with `current_user_can()`
- [ ] No API keys or secrets are output to the browser in any HTML/JS
- [ ] No `var_dump()`, `print_r()`, or `error_log()` calls left in production paths
- [ ] `uninstall.php` checks `WP_UNINSTALL_PLUGIN` constant before running
- [ ] All cron callbacks are unscheduled on deactivation
- [ ] Custom DB table uses WordPress table prefix and `dbDelta()` for creation

### WordPress Coding Standards Checklist:

- [ ] All functions and classes use `wsp_` prefix to avoid collisions
- [ ] Text strings wrapped in `__()` or `_e()` with text domain `wp-social-publisher`
- [ ] All files have proper PHP docblocks
- [ ] No closing `?>` PHP tags at end of files
- [ ] Proper use of WordPress hooks (not direct function calls for extensibility)

---

## PHASE 11 — DEPLOYMENT PACKAGING

After the audit is complete:

1. Create `readme.txt` in WordPress.org format:

```
=== WP Social Publisher ===
Contributors: yourname
Tags: social media, facebook, instagram, linkedin, twitter, cross-posting
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later

== Description ==
Publish WordPress posts to Facebook, Instagram, LinkedIn, and X in one click.

== Installation ==
1. Upload the plugin files to /wp-content/plugins/wp-social-publisher
2. Activate the plugin through Plugins menu in WordPress admin
3. Go to Settings > WP Social Publisher and add your API credentials
4. Edit any post and check the Social Media sidebar to select channels

== Frequently Asked Questions ==
= Does Instagram require an image? =
Yes. Instagram Graph API requires at least one image. The plugin uses your featured image.
Posts without a featured image will be skipped for Instagram with a log entry.

= Where do I get the Facebook Page Access Token? =
In Meta for Developers, create an app, add Pages API, and generate a long-lived page token.

== Changelog ==
= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.0.0 =
Initial release.
```

2. Create a `.distignore` file listing files to exclude from the zip:
```
.git
.gitignore
node_modules
tests/
phpunit.xml
assets/src/
DECISIONS.md
*.log
.DS_Store
Thumbs.db
```

3. Create a shell script `build-zip.sh`:
```bash
#!/bin/bash
# Run from plugin root: bash build-zip.sh
PLUGIN_SLUG="wp-social-publisher"
VERSION=$(grep "Version:" wp-social-publisher.php | awk '{print $3}')
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${ZIP_NAME}..."

# Exclude files in .distignore
EXCLUDES=$(cat .distignore | sed 's/^/--exclude=/' | tr '\n' ' ')

zip -r "../${ZIP_NAME}" . $EXCLUDES

echo "Done! File: ../${ZIP_NAME}"
echo "Upload this zip via WordPress Admin > Plugins > Add New > Upload Plugin"
```

4. Run `bash build-zip.sh` to produce the final deployable zip file.

5. Output a final `DEPLOYMENT_CHECKLIST.md`:

```markdown
# Deployment Checklist — WP Social Publisher v1.0.0

## Before Uploading to Live Site

### API Setup (complete these FIRST — takes 1-3 days)
- [ ] Facebook Developer App created at developers.facebook.com
- [ ] Facebook app has Pages API + Instagram Graph API permissions approved
- [ ] Long-lived Page Access Token generated (valid 60 days)
- [ ] Instagram account is Business or Creator type
- [ ] Instagram account linked to Facebook Page
- [ ] LinkedIn Developer App created at linkedin.com/developers
- [ ] LinkedIn OAuth 2.0 flow completed, access token obtained
- [ ] X Developer account approved at developer.x.com
- [ ] X app has Read + Write OAuth permissions enabled

### WordPress Site Prerequisites
- [ ] WordPress version 5.8 or higher
- [ ] PHP version 7.4 or higher
- [ ] Site is publicly accessible (Instagram requires public image URLs)
- [ ] WP Cron is running (verify with WP Crontrol plugin or server cron)
- [ ] HTTPS enabled (required by all social APIs)

### Upload & Activate
- [ ] Backup live site and database before activating
- [ ] Upload wp-social-publisher-1.0.0.zip via Plugins > Add New > Upload Plugin
- [ ] Activate plugin
- [ ] Visit Settings > WP Social Publisher
- [ ] Enter credentials for each platform
- [ ] Use "Test Connection" buttons to verify each platform
- [ ] Create a test draft post, check all social channels, publish
- [ ] Verify entries appear in Social Media > Activity Log
- [ ] Check social accounts to confirm posts arrived

### Post-Activation
- [ ] Confirm WP Cron job `wsp_token_check` is scheduled (check WP Crontrol)
- [ ] Set calendar reminder to refresh FB + LinkedIn tokens before 60-day expiry
- [ ] Review Activity Log after first few posts for any errors
```

---

## IMPORTANT IMPLEMENTATION NOTES

1. **No external PHP libraries** — use only WordPress core functions and PHP built-ins.
   OAuth signatures, HTTP requests (`wp_remote_post`), JSON handling — all native.

2. **No npm/node required** — the Gutenberg sidebar JS must work without a build step.
   Write it as a plain IIFE using `wp.*` globals.

3. **Graceful degradation** — if any platform fails, the others must still publish.
   Never let one platform failure block another.

4. **WP Cron dependency** — document clearly that WP Cron must be running. On some
   hosts it is disabled. Provide an admin notice if `DISABLE_WP_CRON` is true.

5. **Error visibility** — all API failures must be visible in the Activity Log.
   Silent failures are unacceptable.

6. **Idempotency** — if the same post is published twice (e.g. un-publish + re-publish),
   the plugin should NOT post to social media again. Check the log before dispatching.

7. **Instagram image URL** — the featured image must be publicly accessible. On local
   or staging sites it will fail. Add a check and a clear admin notice.

8. **X API rate limits** — free tier allows 1,500 tweets/month. Add a counter in the
   admin dashboard showing current month usage.

---

## OUTPUT EXPECTATIONS

When complete, confirm the following exist and are non-empty:

- [ ] All PHP class files in `includes/` and `admin/`
- [ ] `assets/build/sidebar.js` — working Gutenberg sidebar
- [ ] `assets/admin.css` — settings and log page styles
- [ ] `tests/` — all 4 test files + bootstrap + phpunit.xml
- [ ] `readme.txt` — WordPress.org format
- [ ] `build-zip.sh` — packaging script
- [ ] `DECISIONS.md` — all assumptions documented
- [ ] `DEPLOYMENT_CHECKLIST.md` — pre-launch checklist
- [ ] Final zip file `wp-social-publisher-1.0.0.zip` ready to upload

Total estimated files: ~25 PHP files, 3 JS/CSS files, 5 documentation files.

Begin with Phase 1 (scaffold) and proceed through all phases without stopping.
```
