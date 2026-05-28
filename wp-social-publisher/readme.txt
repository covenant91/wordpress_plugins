=== WP Social Publisher ===
Contributors: yourname
Tags: social media, facebook, instagram, linkedin, twitter, cross-posting
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish WordPress posts to Facebook, Instagram, LinkedIn, and X in one click.

== Description ==

WP Social Publisher lets you cross-post to Facebook, Instagram, LinkedIn, and X (Twitter)
directly from the WordPress post editor. It works with both the Classic Editor and Gutenberg.

Features:

* Per-post platform selection with optional custom captions
* Live character counter per platform
* Async publishing via WP Cron (never slows down your save action)
* Activity log with status tracking (sent / failed / skipped / pending)
* Retry failed posts from the log dashboard
* Token expiry alerts via admin email
* No external PHP libraries required

== Installation ==

1. Upload the plugin files to /wp-content/plugins/wp-social-publisher
2. Activate the plugin through the Plugins menu in WordPress admin
3. Go to Social Media > Settings and add your API credentials for each platform
4. Edit any post, check the platforms in the Social Media sidebar, and publish

== Frequently Asked Questions ==

= Does Instagram require an image? =
Yes. The Instagram Graph API requires at least one image. The plugin uses your featured image.
Posts without a featured image will be skipped for Instagram with a log entry explaining why.

= Where do I get the Facebook Page Access Token? =
In Meta for Developers (developers.facebook.com), create an app, add Pages API + Instagram Graph
API permissions, and generate a long-lived page access token (valid 60 days).

= Does X (Twitter) have a post limit? =
The X free developer tier allows approximately 1,500 tweets per month. The plugin tracks your
monthly count and displays it on the settings page.

= Does the plugin work on local or staging sites? =
Publishing to Facebook, LinkedIn, and X will work anywhere. Instagram requires a publicly
accessible featured image URL, so it will be skipped on local/staging sites.

= What happens if WP Cron is disabled? =
The plugin will display an admin notice warning. You can set up a server-side cron job to call
wp-cron.php on a schedule to replace WP Cron.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
