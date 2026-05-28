# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**WP Social Publisher** — a WordPress plugin that cross-posts to Facebook, Instagram, LinkedIn, and X (Twitter) directly from the post editor. The full build specification lives in [claude-code-wp-social-publisher-prompt.md](claude-code-wp-social-publisher-prompt.md). All 11 implementation phases are defined there.

The plugin is built into a `wp-social-publisher/` subdirectory.

## Build & Test Commands

These commands run from inside `wp-social-publisher/`:

```bash
# Run all PHPUnit tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/test-publisher.php

# Check WordPress coding standards
vendor/bin/phpcs --standard=WordPress .

# Fix auto-fixable coding standard violations
vendor/bin/phpcbf --standard=WordPress .

# Build deployment zip (run from plugin root)
bash build-zip.sh
```

WP_TESTS_DIR must point to a WordPress test library (typically `/tmp/wordpress-tests-lib`).

## Architecture

### Publishing Flow

1. `transition_post_status` hook fires → `WSP_Publisher` checks if transitioning TO `publish`
2. Publisher reads `_wsp_channels` (selected platforms) and `_wsp_captions` per post
3. For each platform, schedules a `wp_schedule_single_event()` with 5-second delay (prevents publish timeout)
4. Cron events: `wsp_publish_facebook_{post_id}`, `wsp_publish_instagram_{post_id}`, `wsp_publish_linkedin_{post_id}`, `wsp_publish_twitter_{post_id}`
5. Each cron event calls the platform API class, then writes result to `{prefix}wsp_post_log` via `WSP_Post_Log`

Idempotency: before dispatching, check the log — do NOT post again if already published (handles un-publish + re-publish).

### Platform API Classes

Each class in `includes/` implements a single `publish( $post_id, $caption )` method returning:

```php
[ 'success' => bool, 'social_id' => string|null, 'error' => string|null ]
```

- **Facebook**: Graph API v18.0, `/{PAGE_ID}/feed`; uses `/photos` endpoint when featured image exists
- **Instagram**: Two-step publish (create container → publish container); skip with `status=skipped` log entry if no featured image — do NOT fail silently
- **LinkedIn**: UGC Posts API, supports both Person URN and Organization URN
- **Twitter/X**: API v2 `/tweets`; OAuth 1.0a signature built from scratch (HMAC-SHA1, no external library); 280 char limit; X API free tier counter in admin dashboard

All HTTP requests use WordPress's `wp_remote_post()`. No external PHP libraries.

### Token Manager

`WSP_Token_Manager` encrypts credentials with AES-256-CBC (`openssl_encrypt`), key derived from `AUTH_KEY` WordPress constant. Daily cron `wsp_token_check` checks expiry and emails admin when ≤ 7 days remain.

### Editor UI

- **Classic editor**: meta box via `WSP_Meta_Box`, saves `_wsp_channels` and `_wsp_captions` post meta
- **Gutenberg**: standalone IIFE sidebar in `assets/build/sidebar.js` — uses `wp.*` globals only, **no build step, no npm**. Write as ES5-compatible code.

### Admin

- Settings page under `Settings > WP Social Publisher` — tabbed: Facebook/Instagram, LinkedIn, X, Defaults
- Activity log via `WP_List_Table` subclass — filterable by platform/status/date, with retry for failed posts, bulk delete, auto-purge via cron
- `manage_options` capability required for settings

### Database

Custom table `{prefix}wsp_post_log` created via `dbDelta()` on activation. Columns: `id`, `post_id`, `platform`, `status` (sent/failed/skipped/pending), `social_id`, `error_msg`, `caption`, `created_at`.

## Naming Conventions

- PHP functions and classes: `wsp_` prefix (e.g., `WSP_Publisher`, `wsp_get_caption()`)
- CSS classes: `smp-` prefix
- All user-facing strings wrapped in `__()` / `_e()` with text domain `wp-social-publisher`
- No closing `?>` PHP tags at end of files

## Security Requirements

Every AJAX handler must call `check_ajax_referer()` and `current_user_can()`. All DB queries use `$wpdb->prepare()`. All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`. All `$_POST`/`$_GET` sanitized before use. `uninstall.php` must check `WP_UNINSTALL_PLUGIN` before running.

## Key Constraints

- **No external PHP libraries** — WordPress core and PHP built-ins only
- **No npm/node** — Gutenberg sidebar is plain IIFE, no `@wordpress/scripts` build
- **Graceful degradation** — one platform failure must not block other platforms
- **WP Cron required** — show `admin_notices` warning if `DISABLE_WP_CRON` is true; Instagram requires a publicly accessible image URL (warn on local/staging sites)
