# WP VisitChart — Complete User Manual

> Version 2.5.8 · Created by Jens Hummelmose · Public Domain (Unlicense)
> GitHub: [hummelmose/wp-visitchart](https://github.com/hummelmose/wp-visitchart)

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Upgrading](#4-upgrading)
5. [The Admin Dashboard](#5-the-admin-dashboard)
   - 5.1 [Sticky Live Bar](#51-sticky-live-bar)
   - 5.2 [Traffic Graph](#52-traffic-graph)
   - 5.3 [Most Active Pages Right Now](#53-most-active-pages-right-now)
   - 5.4 [Most Visited Today](#54-most-visited-today)
   - 5.5 [Traffic Sources Today](#55-traffic-sources-today)
   - 5.6 [Devices Today](#56-devices-today)
   - 5.7 [Bots Detected](#57-bots-detected)
   - 5.8 [Top Referring Domains](#58-top-referring-domains)
6. [Mobile Page](#6-mobile-page)
7. [WordPress Dashboard Widget](#7-wordpress-dashboard-widget)
8. [Admin Bar Counter](#8-admin-bar-counter)
9. [Pageviews Column in Posts List](#9-pageviews-column-in-posts-list)
10. [Settings](#10-settings)
11. [Badges: Trending and Featured](#11-badges-trending-and-featured)
12. [Data and Privacy](#12-data-and-privacy)
13. [Database Tables](#13-database-tables)
14. [Performance and Caching](#14-performance-and-caching)
15. [Compatibility](#15-compatibility)
16. [Frequently Asked Questions](#16-frequently-asked-questions)

---

## 1. Introduction

WP VisitChart is a self-hosted, real-time analytics plugin for WordPress inspired by Chartbeat. It shows live visitor counts, today's traffic graph compared to the same weekday last week, traffic source breakdowns, device statistics, trending articles, and per-post pageview tracking — all running entirely on your own server with no third-party services, no subscriptions, and no data leaving your site.

**Key principles:**

- All data is stored in your own database
- No external analytics services or tracking pixels
- No cookies required for core functionality
- Public domain (Unlicense) — use it however you like

---

## 2. Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 5.8 |
| PHP | 7.4 |
| MySQL | 5.7 |
| MariaDB | 10.3 (alternative to MySQL) |

---

## 3. Installation

1. Download `wp-visitchart.zip` from the [Releases page](https://github.com/hummelmose/wp-visitchart/releases)
2. In your WordPress admin go to **Plugins → Add Plugin → Upload Plugin**
3. Select the zip file and click **Install Now**
4. Click **Activate Plugin**
5. Go to **WP VisitChart** in the left admin menu

The plugin creates three database tables automatically on activation:

- `wp_lstats_heartbeats` — raw visitor heartbeat data
- `wp_lstats_sessions` — one row per session with pre-computed traffic source and referrer domain
- `wp_lstats_post_views` — per-article daily and total pageview counts

---

## 4. Upgrading

1. Download the latest `wp-visitchart.zip` from the [Releases page](https://github.com/hummelmose/wp-visitchart/releases)
2. Go to **Plugins → Add Plugin → Upload Plugin**
3. Upload the zip file and click **Replace current with uploaded**
4. Activate the plugin

Database schema changes are applied automatically via `dbDelta()` on first load after upgrade. No manual SQL is required.

**Note on traffic source data after upgrading to v2.3.0 or later:** The `lstats_sessions` table introduced in v2.3.0 fills from the first visit after upgrade. Traffic source and referring domain numbers will repopulate within a few minutes of normal traffic. Historical data is not backfilled.

---

## 5. The Admin Dashboard

Navigate to **WP VisitChart** in the left admin menu to access the full dashboard. The dashboard refreshes all data automatically without requiring a page reload.

### 5.1 Sticky Live Bar

A fixed bar at the top of the admin dashboard shows the current live visitor count at all times. The number flashes briefly when it changes. The bar remains visible while scrolling through the dashboard.

**Update interval:** Every 10 seconds.

**What counts as a live visitor:** Any unique browser session that has sent a heartbeat signal within the last 120 seconds.

### 5.2 Traffic Graph

The full-width graph shows visitors and pageviews in 5-minute intervals for the current day, overlaid with the same weekday from the previous week for comparison.

**Lines shown:**

| Line | Description |
|---|---|
| Blue solid | Unique visitors today |
| Red dashed | Pageviews today |
| Grey solid | Unique visitors, same weekday last week |
| Grey dashed | Pageviews, same weekday last week |

**Tooltip:** Touch or hover over the graph to see exact numbers for a specific 5-minute interval. On the mobile page, the tooltip auto-dismisses 5 seconds after you lift your finger.

**Update interval:** Every 60 seconds (30-second server cache).

**Data retention:** 8 days of raw heartbeat data is kept, providing one full week of comparison data.

### 5.3 Most Active Pages Right Now

Lists the top 10 pages with active visitors in the current 120-second window, sorted by live visitor count descending.

**Badges:**

- 🔥 **Trending** — shown when a page has at least 3 active visitors AND its count has grown by at least 50% compared to the previous 120-second window
- ⭐ **Featured** — shown when the article belongs to the category selected as the Featured Category in settings (see [Section 10](#10-settings) and [Section 11](#11-badges-trending-and-featured))

**Update interval:** Every 10 seconds (8-second server cache).

### 5.4 Most Visited Today

Lists the top 20 pages by pageview count since midnight today, sorted descending.

**Counts:** Based on JavaScript pageview pings — one ping per browser session per article. This means a visitor refreshing an article multiple times counts as one pageview.

**⭐ Featured badge** is shown for articles in the selected Featured Category.

**Update interval:** Every 60 seconds (30-second server cache).

### 5.5 Traffic Sources Today

Shows the breakdown of unique visitor sessions since midnight, grouped into four categories:

| Category | What it includes |
|---|---|
| **Direct** | No referrer, or internal navigation from your own domain |
| **Search engines** | Google, Bing, Yahoo, DuckDuckGo, Yandex, Baidu, and other search referrers |
| **Social media** | Facebook (incl. fbclid links), Instagram, Twitter/X, LinkedIn, Reddit, TikTok, Pinterest, YouTube |
| **Other websites** | All other external referrers |

UTM parameters in URLs take precedence over the referrer header. For example, `?utm_source=facebook` is classified as Social even if the referrer header is missing.

**Update interval:** Every 60 seconds (30-second server cache).

### 5.6 Devices Today

Shows the breakdown of unique visitor sessions since midnight by device type:

| Device | Detection method |
|---|---|
| **Mobile** | User-agent string analysis |
| **Tablet** | User-agent string analysis |
| **Desktop** | Default for all other user-agents |

**Update interval:** Every 60 seconds (30-second server cache).

### 5.7 Bots Detected

Lists bot user-agents detected in the current 120-second window. Bots are identified by matching their user-agent string against a list of known crawlers and tools including Googlebot, Bingbot, AhrefsBot, SemrushBot, GPTBot, ClaudeBot, and many others.

The heading shows no count — the full list is displayed below the heading.

**Update interval:** Every 10 seconds (8-second server cache).

### 5.8 Top Referring Domains

Lists the top 15 external domains that sent visitors today, sorted by session count.

- Facebook traffic via `fbclid` URL parameter is labelled `facebook.com (fbclid)` even when the referrer header is absent
- UTM source values are shown with ` (utm)` suffix
- `www.` prefixes are stripped from all domain names

**Update interval:** Every 60 seconds (30-second server cache).

---

## 6. Mobile Page

The mobile page is a standalone, login-free page designed to be bookmarked on your phone or added to your home screen. It shows the same live data as the admin dashboard in a mobile-optimised layout.

**Access:** Go to **WP VisitChart → Settings** to find your unique mobile page URL and copy it to your clipboard using the clipboard icon.

**Security:** Access is protected by a secret token in the URL. Only people with the URL can view the page. You can reset the token at any time in Settings, which immediately invalidates the old URL.

**Layout (top to bottom):**

1. Sticky live visitor count (pinned to top while scrolling)
2. Visitors today graph (5-minute intervals with last week comparison)
3. Most Active Pages Right Now (with 🔥 and ⭐ badges)
4. Most Visited Today (with ⭐ badge)
5. Traffic Sources Today
6. Devices Today
7. Bots Detected
8. Top Referring Domains

**Graph tooltip:** Tap the graph to see exact numbers for a 5-minute interval. The tooltip automatically disappears 5 seconds after you lift your finger.

---

## 7. WordPress Dashboard Widget

A widget titled **WP VisitChart – Top 20 i dag** appears on the main WordPress dashboard (`/wp-admin/`). It shows the 20 most read articles today, sorted by today's pageview count.

**Columns:**

| Column | Description |
|---|---|
| Article | Article number and title, linked to the post |
| I dag | Pageviews today (blue, bold) — the sort column |
| Total | All-time total pageviews including today |

A link to the full WP VisitChart dashboard is shown at the bottom of the widget.

**Visibility:** The widget only appears when the **Pageviews column** setting is enabled in WP VisitChart Settings.

**Data source:** Reads directly from `lstats_post_views` — the same table that powers the pageviews column in the Posts list.

---

## 8. Admin Bar Counter

When enabled, a live visitor count appears in the WordPress admin bar. It is visible on all admin pages and on the frontend when you are logged in.

- Clicking the counter takes you directly to the WP VisitChart dashboard
- The counter refreshes every 10 seconds
- Can be toggled on or off in Settings

---

## 9. Pageviews Column in Posts List

When enabled, a chart icon column appears in the WordPress Posts and Pages admin lists, showing each article's pageview statistics.

**Display:** Total pageviews and today's count are shown for each article.

**Sorting:** The column is sortable — click the header icon to sort all posts by total pageviews.

**Counting method:** One JavaScript ping per browser session per article. The ping fires once when a visitor first views the article in a session and does not fire again on refresh or revisit within the same session.

**Data rollover:** Today's count (`views_today`) is automatically moved into the total (`views_total`) at the start of each new day on the first visit to that article.

---

## 10. Settings

Navigate to **WP VisitChart → Settings** to configure the plugin.

### Admin Bar Counter

**Option:** Enable or disable the live visitor count in the WordPress admin bar.

**Default:** Enabled.

### Mobile Page

**Option:** Enable or disable the public mobile statistics page.

**Default:** Enabled.

When enabled, your unique mobile page URL is shown with a clipboard copy button. Click the button to copy the URL instantly.

**Reset access code:** Clicking **Reset access code** generates a new secret token, immediately invalidating the previous mobile page URL. Share the new URL with anyone who needs access.

### Pageviews Column

**Option:** Enable or disable the pageviews column in the Posts and Pages admin lists, the WordPress dashboard widget, and the per-article pageview tracking system.

**Default:** Enabled.

### Exclude Logged-in Users

**Option:** When enabled, logged-in WordPress users are completely excluded from all tracking. Neither heartbeats nor pageview pings are sent for logged-in sessions.

**Recommended for:** Editorial and news sites where editors and authors frequently visit their own content and should not inflate the statistics.

**Default:** Disabled.

### Featured Category

**Option:** Select one WordPress category. Articles in the selected category will display a ⭐ badge in the Most Active Pages and Most Visited Today lists on both the admin dashboard and the mobile page.

Select **— None —** to disable the featured category feature entirely.

**Default:** None.

---

## 11. Badges: Trending and Featured

Both badges appear in the Most Active Pages Right Now and Most Visited Today lists, positioned between the article title and the visitor count so they are always visible regardless of screen width.

### 🔥 Trending Badge

Appears in **Most Active Pages Right Now** only.

**Conditions (all must be met):**
- The page has at least 3 active visitors right now
- The current visitor count is at least 50% higher than in the previous 120-second window
- The current count is strictly higher than the previous count

### ⭐ Featured Badge

Appears in both **Most Active Pages Right Now** and **Most Visited Today**.

**Condition:** The article belongs to the category selected as Featured Category in Settings.

Configure the featured category under **WP VisitChart → Settings → Featured Category**.

---

## 12. Data and Privacy

### What data is collected

WP VisitChart collects the following data per visitor session:

- Session ID (generated in the browser's `sessionStorage`, not stored as a cookie)
- Page URL and referring URL
- User-agent string (for device and bot detection)
- IP address (used only server-side to generate a session ID for bot tracking, not stored)
- Timestamp

### Where data is stored

All data is stored exclusively in your WordPress database. No data is sent to any external service.

### Data retention

Raw heartbeat data and session data are automatically deleted after **8 days** by an hourly cleanup process. Post pageview counts (`lstats_post_views`) are kept indefinitely.

### Cookies

WP VisitChart does not set any cookies. Session IDs are stored in the browser's `sessionStorage`, which is automatically cleared when the browser tab is closed.

### Bot traffic

Known bots and crawlers are detected via user-agent matching and are excluded from all visitor statistics. Bot counts are shown separately in the Bots Detected section.

---

## 13. Database Tables

WP VisitChart creates three tables in your WordPress database (using your configured table prefix, default `wp_`):

### `wp_lstats_heartbeats`

Stores raw heartbeat signals from active visitors.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT (PK) | Auto-increment primary key |
| `post_id` | BIGINT | WordPress post/page ID (0 for non-post pages) |
| `session_id` | VARCHAR(64) | Unique session identifier |
| `url` | VARCHAR(500) | Current page URL |
| `is_bot` | TINYINT | 1 if bot, 0 if human visitor |
| `source` | VARCHAR(20) | `heartbeat` (JS) or `pageload` (server-side, bots only) |
| `user_agent` | VARCHAR(255) | Browser/bot user-agent string |
| `referrer` | VARCHAR(255) | Referring URL |
| `device_type` | VARCHAR(20) | `mobile`, `tablet`, or `desktop` |
| `created_at` | DATETIME | Timestamp of the heartbeat |

**Indexes:** Primary key, `idx_created_at`, `idx_post_id`, `idx_session`, `idx_bot_source_time (is_bot, source, created_at)`, `idx_graph_covering (is_bot, source, created_at, session_id, post_id)`

**Retention:** 8 days, cleaned hourly by WP Cron.

### `wp_lstats_sessions`

Stores one row per unique visitor session. Written on the first heartbeat of each session using `INSERT IGNORE` — subsequent heartbeats from the same session are silently ignored.

| Column | Type | Description |
|---|---|---|
| `session_id` | VARCHAR(64) (PK) | Unique session identifier |
| `referrer` | VARCHAR(255) | Referring URL |
| `url` | VARCHAR(500) | Landing page URL |
| `device_type` | VARCHAR(20) | Device type |
| `is_bot` | TINYINT | 1 if bot, 0 if human |
| `category` | VARCHAR(10) | Pre-computed traffic source: `direct`, `search`, `social`, or `other` |
| `domain` | VARCHAR(255) | Pre-computed referring domain |
| `count_date` | DATE | Date of the session |
| `first_seen` | DATETIME | Timestamp of first heartbeat |

**Indexes:** Primary key (session_id), `idx_date_bot (count_date, is_bot)`, `idx_first_seen`

**Retention:** 8 days, cleaned hourly by WP Cron.

### `wp_lstats_post_views`

Stores daily and total pageview counts per article.

| Column | Type | Description |
|---|---|---|
| `post_id` | BIGINT (PK) | WordPress post/page ID |
| `views_today` | BIGINT | Pageviews for the current day |
| `views_total` | BIGINT | Accumulated total (excluding today) |
| `count_date` | DATE | Date of the current `views_today` count |
| `updated_at` | DATETIME | Last update timestamp |

**Note:** The true all-time total for an active day is `views_total + views_today`. The `views_today` count is transferred into `views_total` at the start of each new day on the first visit.

---

## 14. Performance and Caching

### Caching layers

WP VisitChart uses WordPress transients for server-side caching:

| Data | Cache duration |
|---|---|
| Live visitor count | 8 seconds |
| Traffic graph data | 30 seconds |
| Most Visited Today | 30 seconds |
| Traffic Sources & Domains | 30 seconds |
| Device breakdown | 30 seconds |

### WP Rocket compatibility

WP VisitChart is compatible with WP Rocket. The following measures ensure live data is always served correctly:

- All REST API endpoints are excluded from WP Rocket's page cache
- REST endpoints are excluded from WP Rocket's preloader crawler
- Transients are automatically cleared when WP Rocket purges cache after post publication
- `wp_cache_delete()` is called after every transient write to prevent Redis/Memcached from serving stale data beyond the TTL

### Redis / Memcached

If your site uses a persistent object cache (Redis or Memcached), WP VisitChart calls `wp_cache_delete()` without a group parameter after each transient write, ensuring the object cache is evicted correctly regardless of the cache driver.

### Database indexes

WP VisitChart maintains optimised indexes on `lstats_heartbeats` to keep queries fast even on large tables:

- A **covering index** `idx_graph_covering (is_bot, source, created_at, session_id, post_id)` allows the graph query to be resolved entirely from the index without touching table rows
- Traffic source queries run against `lstats_sessions` (typically ~25,000 rows per day) rather than `lstats_heartbeats` (potentially millions of rows)

---

## 15. Compatibility

### Caching plugins

| Plugin | Compatible |
|---|---|
| WP Rocket | ✅ Yes — built-in hooks and cache exclusions |
| Cloudflare | ✅ Yes — REST endpoints return no-cache headers |
| W3 Total Cache | ✅ Yes |
| LiteSpeed Cache | ✅ Yes |

### Object cache

Compatible with Redis, Memcached, and any other object cache driver that implements the WordPress object cache API.

### Other analytics plugins

WP VisitChart runs independently and does not conflict with Google Analytics, Matomo, Fathom, or other analytics tools. You can run multiple analytics tools simultaneously.

### Multisite

Not tested on WordPress Multisite installations. Single-site installations only.

---

## 16. Frequently Asked Questions

**Why is my live visitor count higher than Matomo or Google Analytics?**

Live counters and traditional analytics measure fundamentally different things. WP VisitChart counts unique browser sessions active in the last 120 seconds. A session expires when a browser tab is closed, so the same visitor opening a new tab starts a new session. Traditional analytics tools use cookies that persist for 30 minutes or more, collapsing many of these into a single visit. A count 1.5–2× higher than Matomo is expected and normal.

**Why are traffic source numbers empty after upgrading?**

The `lstats_sessions` table introduced in v2.3.0 fills from the first visit after upgrade. Data repopulates within a few minutes on an active site. Historical traffic source data from before the upgrade cannot be recovered.

**Why is "Total" sometimes lower than "Today" in the dashboard widget?**

`views_total` accumulates yesterday's count and all previous days but does not include today's count until midnight. The dashboard widget and posts column both display `views_total + views_today` to show the correct all-time total including today.

**Does the plugin work without WP Rocket?**

Yes. The WP Rocket hooks fire only if WP Rocket is installed. Without a persistent object cache, transients expire naturally via their TTL and no cache-busting is needed.

**Can I use this on multiple sites?**

Yes. Install the plugin on each site independently. Each installation has its own database tables and settings.

**How do I stop editors from appearing in the statistics?**

Enable **Exclude Logged-in Users** in WP VisitChart → Settings. This stops all tracking for logged-in WordPress users entirely.

**How do I access the mobile page?**

Go to **WP VisitChart → Settings**. Your unique mobile page URL is shown there with a clipboard copy button. Bookmark the URL on your phone or add it to your home screen.

**Can I reset the mobile page URL?**

Yes. Click **Reset access code** in Settings. This generates a new secret token and immediately invalidates the old URL.

**Will upgrading from version 1.x work without issues?**

Yes. The database schema is updated automatically via `dbDelta()` on first load after upgrade. All existing heartbeat and pageview data is preserved. The only thing that needs time to repopulate is the traffic sources breakdown, which fills within minutes.

---

*WP VisitChart v2.5.5 · Public Domain (Unlicense) · Created by Jens Hummelmose*
*Source: [github.com/hummelmose/wp-visitchart](https://github.com/hummelmose/wp-visitchart)*
