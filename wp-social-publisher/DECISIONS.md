# DECISIONS.md — WP Social Publisher

All assumptions and design decisions made during implementation.

---

## Architecture

**Async via WP Cron (5-second delay)**
Each platform publish is scheduled as a single cron event 5 seconds after post publish.
This prevents the post save action from timing out due to slow API calls.
Assumption: WP Cron is enabled. An admin notice is shown if `DISABLE_WP_CRON` is true.

**No external PHP libraries**
All HTTP requests use `wp_remote_post()`. OAuth 1.0a for X is implemented from scratch
using PHP's `hash_hmac`, `openssl_encrypt`, and `random_bytes`. No Composer dependencies.

**No npm/build step for Gutenberg**
The Gutenberg sidebar is an ES5-compatible IIFE using `wp.*` globals.
This avoids requiring Node.js on the development machine and keeps the plugin self-contained.

---

## Token Storage

Tokens are encrypted with AES-256-CBC using a 32-byte key derived from `hash('sha256', AUTH_KEY . SECURE_AUTH_KEY)`.
The IV is prepended to the ciphertext before base64-encoding.
Assumption: `AUTH_KEY` and `SECURE_AUTH_KEY` are defined in `wp-config.php` (standard WordPress).
If not defined, fallback strings are used — sites without these constants are already insecure.

Password fields on the settings page are left blank on page load. Submitting a blank field
preserves the existing stored (encrypted) value. Only non-empty submissions overwrite.

---

## Idempotency

The publisher checks `wsp_post_log` for a `sent` entry before dispatching.
If a post is un-published and re-published, social posts are NOT duplicated.
Assumption: this is the desired behaviour. If re-posting on update is needed in future,
an option can be added to the Defaults tab.

---

## Instagram

Instagram requires a publicly accessible image URL (no local/staging support).
If no featured image is set, the entry is logged as `skipped` (not `failed`).
A local URL check warns admins via the return value; the admin notice in the settings
page also surfaces this for staging environments.
Assumption: Instagram posts are always image-based (no text-only Instagram posts).

---

## LinkedIn URN

LinkedIn supports posting as either a Person or an Organization.
Settings store the numeric ID and a type toggle (`person` / `organization`).
The URN is built at publish time: `urn:li:person:{id}` or `urn:li:organization:{id}`.

---

## X (Twitter)

URLs always appended to tweet text. X wraps all URLs as 23-character t.co links,
so the available text space is `280 - 23 - 1 (space) = 256` characters.
Caption is truncated to 256 chars and the post URL is appended.
Monthly tweet count is stored in a transient keyed by `Y_m` (year and month).

---

## Log Retention

Default: 90 days. Configurable in Settings > Defaults.
Purge runs daily via `wsp_purge_logs` cron event, called from `WSP_Publisher::purge_logs()`.

---

## Classic Editor vs Gutenberg

Both editors store data in the same post meta keys (`_wsp_channels`, `_wsp_captions`).
`register_post_meta` with `show_in_rest: true` exposes them to Gutenberg's REST-based meta saving.
The meta box `save_post` hook handles the classic editor path.
Both paths include nonce verification and capability checks.

---

## Settings Page

Uses a custom POST-based form rather than the WordPress Settings API `register_setting` callback
to allow per-tab saves and fine-grained control over encrypted field handling.
Trade-off: slightly more boilerplate, but avoids Settings API limitations with password fields.
