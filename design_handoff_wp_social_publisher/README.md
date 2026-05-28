# Handoff: WP Social Publisher — Admin UI

## Overview

WP Social Publisher is a WordPress plugin that lets editors cross-post content to **Facebook, Instagram, LinkedIn, and X (Twitter)** directly from the post editor. This handoff covers the **admin-facing UI** of the plugin across four screens:

1. **Overview / Dashboard** — Token health, monthly API usage, channel breakdown, recent activity
2. **Post Editor (Gutenberg)** — Social Media sidebar panel with per-platform channel selection, custom captions, and live character counters
3. **Activity Log** — `WP_List_Table`-styled log of every cross-post attempt, with filters, retry actions, and expandable API-response detail
4. **Settings** — Tabbed credentials/config page (Facebook+Instagram / LinkedIn / X / Defaults)

The plugin's backend behavior (`includes/` classes, REST endpoints, cron jobs, OAuth flows, DB schema) is **out of scope for this handoff** — implement those per the original `claude-code-wp-social-publisher-prompt.md` spec. This document defines only what the user sees.

---

## About the Design Files

The files in this bundle are **design references created in HTML/React** — interactive prototypes showing intended look and behavior, not production code to copy directly into the plugin.

The task is to **recreate these designs inside WordPress's existing admin environment**:

- The **Settings page**, **Activity Log**, and **Overview** screens should be built with **PHP + WordPress's native admin styles** (`wp-admin` CSS classes, `Settings API`, `WP_List_Table`, etc.) — not as a standalone React SPA. The mocks intentionally use WP-Admin's `.postbox`, `.form-table`, `.nav-tab-wrapper`, `.wp-list-table`, and `.notice` classes so the visual vocabulary maps 1:1 to what WordPress provides.
- The **Gutenberg sidebar** (`Social Media` panel) is the one piece that **must be React** — it lives inside the block editor and uses `@wordpress/plugins`, `@wordpress/edit-post`, `@wordpress/components`, and `@wordpress/data`. The prototype's `editor.jsx` mirrors the structure of what should be built with `<PluginSidebar>`, `<PanelBody>`, `<CheckboxControl>`, and `<TextareaControl>`.
- Per the original spec, the Gutenberg sidebar must work **without a build step** — write it as an ES5-compatible IIFE using `wp.*` globals, and save the result to `assets/build/sidebar.js`.

The WP-Admin chrome shown in the prototype (top admin bar, left sidebar menu) is **environment context only** — WordPress provides it automatically; do not rebuild it.

---

## Fidelity

**High-fidelity.** Final colors, spacing, typography, and interaction states are all decided. Recreate pixel-faithfully using WordPress core's `wp-admin` styles where they exist. Where the prototype introduces something WP doesn't ship by default (the colored platform badges, the X usage progress bar, the per-platform character counter, the expandable log row with the dark API-response inspector), match the prototype's visual treatment.

---

## Screens

### 1. Overview (`Social Publisher → Overview`)

**Purpose:** At-a-glance plugin health. Editors land here to confirm tokens are healthy and recent posts went out.

**Layout**
- Standard WP admin `.wrap` container (≈20–24px padding)
- Page heading: 23px regular, share icon left of title
- Optional warning notice (token-expiring) at top — full-width `.notice.notice-warning`
- **4-column stat grid** (responsive to 2-column ≤1100px) — 16px gap
- **2-column grid** below for "Token health" `.postbox` + "Recent activity" `.postbox`
- Final **2-column grid** for "Channel breakdown · 30 days" + "Quick start"

**Stat cards (4)**
1. Posts published — large number + delta vs prior period
2. Cross-posts sent — large number + delta
3. Failures — red number + "N needing retry"
4. **X API usage** — `823 / 1,500` plus a 6px progress bar (warn-amber when ≥50%, danger-red ≥80%)

**Token health postbox**
- One row per platform: brand-color icon tile · platform name · sub-line (page/account/scope) · pill on the right
- Pill states: green "54 days left" (default) / amber "≤14 days" / red "≤7 days" / neutral "No expiry"
- Each row is a horizontal flex with 12px gap, 12px vertical padding, hairline divider between rows
- Header has a "Manage credentials →" link to Settings

**Recent activity postbox**
- Embedded mini `.wp-list-table` with no outer border (columns: platform icon · post title · status pill · relative time)
- Header has a "View full log →" link to Activity Log

**Channel breakdown**
- Per-platform stacked horizontal bar (sent / failed / skipped) with a legend below
- Bar height 14px, fully rounded ends, segments butt-joined

**Quick start postbox**
- Numbered 4-step list explaining the workflow
- Footer with primary "+ New post with social" button and secondary "Open settings" button

---

### 2. Post Editor — Gutenberg Sidebar (`Posts → Edit Post`)

**Purpose:** Editor checks platforms, writes per-platform captions, then hits Publish. The actual cross-post dispatch happens server-side on `transition_post_status`.

**Layout**
- Full-bleed Gutenberg editor mock: 60px header / fluid canvas / 280px right sidebar
- Editor header chrome (logo · add-block · undo/redo · document overview · spacer · saved indicator · preview · **Publish** · settings cog · **plugins icon** · overflow menu)
- The **plugins icon** in the header toggles the Social Media sidebar (highlighted when active — black background, white icon)
- Sidebar has 3 tab buttons: `Post`, `Block`, and a plugin-icon tab that opens the **Social Media** panel

**Social Media panel** (the heart of this screen)
- Panel header: chevron · share-icon · "Social Media" · `{n}/4` counter on the right
- 12px paragraph intro: *"Cross-post this article when you hit Publish. Tokens auto-renewed daily."*
- For each of the 4 platforms, render a `.sm-platform` card:
  - **Header row** (always visible): checkbox · brand-color icon tile (16px) · platform name · meta string (`{limit} chars` when checked)
  - **Body** (only when checked AND not already-sent): textarea (4 rows, or 3 for X) seeded with a sensible per-platform default caption, plus a **character counter** showing `{count} / {limit}` — normal grey at <90% of limit, **warning amber** at ≥90%, **danger red with `−N` overflow** above 100%
- **"Already published" state:** when a post was previously cross-posted to a given platform, that platform's card is locked: checkbox disabled, body collapsed, meta says "sent", and a `✓ Already published · 2h ago` line shows
- **Footer summary block:** light-grey rounded box with "On publish — N channels" and the sentence "Dispatched as async cron jobs · 5s delay · status in Activity Log"

**Sample captions** (use as starting placeholders, not as final copy):

- **Facebook (63,206 char limit):** Newsy lede with the post URL appended.
- **Instagram (2,200 char limit):** Tagline + "Link in bio" + a row of hashtags.
- **LinkedIn (3,000 char limit):** Professional framing, 2–3 paragraphs.
- **X (280 char limit):** Hook + URL. URL is auto-appended at dispatch time, so the textarea reserves space for it.

**Char-counter color thresholds**
- `len < 0.9 * limit` → `var(--wp-text-3)` neutral
- `len ≥ 0.9 * limit` → `var(--wp-warning)` (`#dba617`)
- `len > limit` → `var(--wp-danger)` (`#d63638`), bold, with `· −{overflow}` suffix

---

### 3. Activity Log (`Social Publisher → Activity Log`)

**Purpose:** Editors/admins debug failures, retry failed sends, and audit what went where.

**Layout**
- Standard `WP_List_Table` inside a `.postbox`
- Top toolbar (`.tablenav`) inside the postbox: platform filter · status filter · date-range filter · search input · "N items" displayed-num on the right
- A **conditional bulk-action bar** appears between the toolbar and the table whenever ≥1 row is checked: "{N} selected · Retry · Delete · Clear selection"
- Optional `.notice.notice-error` above the postbox when failures exist in the last hour

**Columns**
| Column     | Width  | Content                                                  |
|------------|--------|----------------------------------------------------------|
| ☐          | 32px   | Bulk-select checkbox                                     |
| Post       | auto   | Post title (link) + row-actions on hover (View / Retry / Edit / Delete) |
| Platform   | 110px  | Colored brand pill (`.plat`)                             |
| Status     | 90px   | `sent` (green) / `failed` (red) / `pending` (amber) / `skipped` (grey) — small pill with leading dot |
| Caption    | flex   | Truncated single line, `title` attr for full hover; if skipped/failed, show the error in red italic instead |
| Social ID  | 160px  | Monospace 11px ID returned by the platform API, or `—`   |
| Date       | 150px  | ISO-style timestamp                                      |
| (actions)  | 80px   | Quick-retry icon button on failed rows                   |

**Row hover** reveals the row-actions row (standard WP behavior — 8px gap, 12px font-size, danger items red).

**Expandable detail** — clicking "View details" in row-actions expands a full-width detail row underneath with a **2-column** grid:
- Left: "Caption sent" — monospace, white card
- Right: "API response" — **dark code block** (`#1d2327` background, `#a3e635` text, 11px monospace) showing a fake HTTP status + JSON body. Different content per status (200 OK / 401 Unauthorized / Skipped / Pending).

**Filters** are client-side in the prototype; in WP, drive them via query args (`?platform=...&status=...&s=...`) and re-query in `prepare_items()`.

**Empty state** — colspan-8 row, 40px padding, centered grey "No matching log entries."

---

### 4. Settings (`Social Publisher → Settings`)

**Purpose:** Add API credentials and configure global defaults.

**Layout**
- Page heading + `.nav-tab-wrapper` with 4 tabs:
  1. **Facebook / Instagram** (default)
  2. **LinkedIn**
  3. **X (Twitter)**
  4. **Defaults**
- Each tab header shows a small brand-color icon to the left of the label
- Each tab renders one or more `.postbox` cards containing a `.form-table`

**Common form patterns**

- **Secret field** — `<input type="password">` with an eye-toggle on the right edge to reveal. Stored encrypted via `WSP_Token_Manager`, never echoed to the browser as plaintext (mocks show bullets + ellipsis, e.g. `EAAGm0PX4ZCpsBAJ...•••••...sLgZD`).
- **Code-style text field** — `.code-text` class applies a monospace stack to make API IDs scannable.
- **Test Connection button** — `Test Connection` → spinner + "Testing…" → success (`✓ Connected · responded in {n}ms`, green) or failure (`! HTTP 401 — OAuth signature did not validate`, red). Resets to idle after ~4s.
- **Connection status pill** in the postbox header: green `✓ Connected` or red `! Auth failed`.

**Tab 1 — Facebook / Instagram**
- Two stacked postboxes: "Facebook Page" and "Instagram (Graph API)".
- Facebook fields: App ID · App Secret · Page Access Token · Page ID · Test Connection
- Instagram section opens with an `.notice.notice-info` explaining the Business-account + featured-image requirements.
- Instagram fields: Instagram User ID · Image source select (Featured image / First image block) · Test Connection
- The Page Access Token row's description switches to amber-bold "Expires in N days — regenerate soon" when the warning state is active.

**Tab 2 — LinkedIn**
- Single postbox with: Client ID · Client Secret · Access Token · Token expiry (read-only, populated from saved data) · Publish-as radio (Personal profile / Company page) · Person URN OR Organization URN (label/placeholder swaps based on radio) · Default visibility select · Test Connection

**Tab 3 — X (Twitter)**
- Single postbox.
- **Open with a warning notice showing monthly usage:** `823 / 1,500 (55%)` and a progress bar. Bar warns amber ≥50%, danger ≥80%.
- Fields: Consumer Key · Consumer Secret · Access Token · Access Token Secret · "Append post URL" toggle · "Upload featured image" toggle · Test Connection

**Tab 4 — Defaults**
- Two postboxes:
  1. **Caption defaults** — 4 rows (one per platform) with default hashtags; "Auto-append post URL" toggle; Caption fallback select (Excerpt / Title / Both)
  2. **Behaviour** — Enabled post types (checkboxes, fed by `get_post_types(['public'=>true])`); Default selected channels (4 checkboxes); Cron delay (number, seconds, default 5); Log retention (number, days, default 90); Expiry email alerts toggle
- Footer: primary "Save Changes" button + secondary "Reset to defaults"

---

## Interactions & Behavior

- **Sidebar navigation** is the standard WP admin pattern: clicking the plugin item in the left menu expands a submenu; submenu items load full-page views via `?page=wp-social-publisher-{slug}`.
- **Tabs** in Settings use the standard WP pattern: anchor links with `?tab=` query param; active tab gets `.nav-tab-active`. (The prototype uses React state — adapt to query-param routing for the real plugin.)
- **Test Connection** is an `admin-ajax.php` (or REST) endpoint that does a single read-only API ping and returns `{ ok, latency, error }`. Nonce verify + `current_user_can('manage_options')` required.
- **Character counter** in the Gutenberg sidebar updates on every keystroke; switch from `Notice`-info to `Notice`-warning component when ≥90% of limit, and to error when over.
- **Already-published lockout** in the sidebar: read the post-log table for this `post_id`; if a row exists with `status='sent'` for a given platform, disable that platform's checkbox and show the timestamp. (Editors can still manually override via row actions — that's handled in the Log.)
- **Retry** action on a failed log row schedules a fresh `wp_schedule_single_event()` with a 5s delay and the same `post_id`/platform args.
- **Bulk delete** in the log uses `$wpdb->query()` with `IN (...)` placeholders — never interpolate IDs.
- **Expandable detail** in the log is purely visual; the API response shown is read from the `error_msg` / `caption` columns already stored on the log row, plus a derived HTTP status string.

---

## State Management

The prototype tracks the following pieces of UI state — translate to whatever pattern the codebase uses:

| State                       | Where it lives                                     | Persistence                                   |
|----------------------------|----------------------------------------------------|-----------------------------------------------|
| Selected channels per post | `_wsp_channels` post meta (array of platform IDs) | `update_post_meta` on save_post               |
| Per-platform captions      | `_wsp_captions` post meta (assoc array)            | same                                          |
| Encrypted credentials       | `wsp_settings` option, AES-256-CBC                | `WSP_Token_Manager::save_token`               |
| Already-published flags    | `{prefix}wsp_post_log` table                       | written by publish-cron callbacks             |
| List-table filters          | URL query args                                     | not persisted; re-read on page load           |
| Test-Connection result      | Transient, 60s TTL keyed by user+platform          | so the UI doesn't refire on every reload      |

---

## Design Tokens

Use WordPress core values where they exist. The prototype tokens (CSS custom properties in `styles.css`) are aligned to WP-Admin's palette:

### Colors

```
--wp-bg:           #f0f0f1   /* admin background */
--wp-surface:      #ffffff
--wp-border:       #c3c4c7
--wp-border-2:     #dcdcde
--wp-text:         #1d2327
--wp-text-2:       #50575e
--wp-text-3:       #787c82
--wp-link:         #2271b1   /* primary link / accent */
--wp-link-hover:   #135e96
--wp-accent:       #2271b1   /* WP "primary" button */
--wp-accent-hover: #135e96
--wp-sidebar-bg:   #1d2327
--wp-sidebar-text: #c3c4c7
--wp-sidebar-hov:  #72aee6
--wp-success:      #00a32a
--wp-warning:      #dba617
--wp-danger:       #d63638
--wp-info:         #72aee6
```

### Brand (platform) colors — use ONLY on platform badges/icons

```
Facebook   #1877f2  (solid)
Instagram  linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)
LinkedIn   #0a66c2  (solid)
X (Twitter)#0f1419  (solid — true X black)
```

### Spacing scale

WP-admin doesn't have a strict scale; the prototype uses `4 · 6 · 8 · 10 · 12 · 14 · 16 · 20 · 24` px.

### Typography

```
Font stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif
Base size:  13px / 1.5 line-height  (WP-admin base — do NOT change)
Page heading h1.wp-heading:  23px / 400 weight
Postbox title:               14px / 600
Form-table label (th):       13px / 600
Description text:            12px / italic / --wp-text-3
Stat-card label:             11px uppercase / 600 / --wp-text-3 / letter-spacing .04em
Stat-card value:             28px / 600
Monospace (API IDs, code):   ui-monospace, "SF Mono", Menlo, Consolas, monospace, 11–13px
Gutenberg post title (mock): Georgia/serif, 32px / 400
```

### Border radius

`4px` default · `8px` on the editor shell · `10px` for status/badge pills · `50%` for icon avatars

### Shadows

```
--shadow-1: 0 1px 1px rgba(0,0,0,.04)         /* postbox resting */
--shadow-2: 0 1px 3px rgba(0,0,0,.08),
            0 1px 2px rgba(0,0,0,.05)         /* editor shell, modals */
```

### Status pills

```
sent     bg #edfaef  fg #00611e
failed   bg #fcebec  fg #8a1f1f
pending  bg #fff8e5  fg #7a5d00
skipped  bg #f0f0f1  fg #50575e
```

(Dark-mode variants in `styles.css` if you choose to ship a dark theme — WP-admin doesn't yet, so this is optional.)

---

## Assets

The prototype uses **inline SVG only** — no raster images, no external icon fonts:

- **WP "W" mark** — used in admin bar and Gutenberg header. Source: WordPress trademark glyph.
- **Generic UI icons** (share, dashboard, posts, media, settings, eye, refresh, alert, etc.) — Feather-style 24×24 strokes at `stroke-width: 1.8–2`. Find these in `icons.jsx` → `Icon` object. Replace with `@wordpress/icons` (`Icon` component + `share`, `cog`, `warning`, etc.) when implementing.
- **Platform brand glyphs** (Facebook f, Instagram camera, LinkedIn in, X) — official monochrome glyphs, rendered on the platform's brand-color tile. In `icons.jsx` → `PIcon` object. These can stay as inline SVG in the plugin; bundle them as a small SVG sprite or PHP-rendered partials.

There are **no photographs, no logos beyond the platform glyphs, and no Anthropic brand assets** in this design.

---

## Files Included

```
WP Social Publisher.html   — entry point; wires up React + Babel + all .jsx
styles.css                 — all CSS (WP-admin tokens, postbox, form-table,
                             wp-list-table, nav-tab, Gutenberg shell, status
                             pills, platform badges, dark theme, density)
app.jsx                    — admin-bar, sidebar nav, route switcher, Tweaks panel
icons.jsx                  — Icon + PIcon SVG sets, PLATFORMS constant,
                             PlatformBadge / PlatformIconSolo components
dashboard.jsx              — Overview screen (stat cards, token health,
                             recent activity, channel breakdown, quick start)
editor.jsx                 — Gutenberg editor mock + SocialMediaPanel
                             (the most important file for the real plugin —
                              port its structure to assets/build/sidebar.js)
log.jsx                    — Activity Log list table + filters + expandable detail
settings.jsx               — Settings tabs (FacebookTab / LinkedInTab /
                             TwitterTab / DefaultsTab) + SecretField +
                             TestConnectionButton
tweaks-panel.jsx           — Prototype-only tweak panel; NOT part of the plugin.
                             Provided so you can toggle warning/error states
                             in the prototype.
```

To preview, open `WP Social Publisher.html` in a browser.

To map prototype → plugin file:

| Prototype          | Real plugin file                            |
|--------------------|----------------------------------------------|
| `dashboard.jsx`    | `admin/dashboard-page.php`                   |
| `editor.jsx` (sidebar panel only) | `assets/src/sidebar.js` → built to `assets/build/sidebar.js` |
| `log.jsx`          | `admin/log-viewer.php` + `class-wsp-post-log.php` (`WP_List_Table`) |
| `settings.jsx` Tab 1 | `admin/partials/settings-facebook.php`     |
| `settings.jsx` Tab 2 | `admin/partials/settings-linkedin.php`     |
| `settings.jsx` Tab 3 | `admin/partials/settings-twitter.php`      |
| `settings.jsx` Tab 4 | `admin/partials/settings-defaults.php`     |
| `styles.css` (subset) | `assets/admin.css`                        |

---

## Reference

The original product spec (DB schema, API class signatures, OAuth flow, cron hooks, testing requirements, packaging script) lives in `claude-code-wp-social-publisher-prompt.md`. **This UI handoff is a companion to that spec — implement the backend per the spec, the UI per this document.**
