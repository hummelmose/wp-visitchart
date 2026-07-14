# WP VisitChart – User Manual

**Version:** 1.9.3  
**Author:** Jens E. Hummelmose  
**Copyright:** © 2026 Jens E. Hummelmose

---

## Table of Contents

1. [What is WP VisitChart?](#what-is-wp-visitchart)
2. [Installation and Activation](#installation-and-activation)
3. [The Dashboard](#the-dashboard)
4. [Settings](#settings)
5. [The Mobile Page](#the-mobile-page)
6. [The Admin Bar](#the-admin-bar)
7. [Pageviews in the Posts List](#pageviews-in-the-posts-list)
8. [How Data is Collected](#how-data-is-collected)
9. [What the Numbers Mean](#what-the-numbers-mean)
10. [Known Limitations](#known-limitations)

---

## What is WP VisitChart?

WP VisitChart is a self-hosted WordPress plugin for real-time traffic analytics, inspired by professional tools like Chartbeat. It shows live visitor counts, current reading activity, and how today's traffic is developing — directly in your WordPress admin, on a bookmarkable mobile page, and in the admin bar at the top of the screen.

The plugin requires no third-party services, no subscriptions, and sends no data outside your own server. Everything runs on your own infrastructure.

---

## Installation and Activation

1. Upload `wp-visitchart.zip` via **Plugins → Add Plugin → Upload Plugin**
2. Activate the plugin
3. WordPress will automatically create the required database tables
4. Go to **WP VisitChart** in the left admin menu

The plugin starts collecting data immediately after activation. There is no setup wizard — the default settings work out of the box.

**Important when upgrading from WP VisiChart:** Since the plugin folder has been renamed, you must deactivate and delete the old plugin before installing WP VisitChart. Your existing data is preserved, as the database tables use the same names.

---

## The Dashboard

Find the dashboard under **WP VisitChart** in the left admin menu.

### Live Visitors Right Now

The large blue number at the top shows the number of unique visitors who have sent an active signal from their browser within the last 120 seconds. The number refreshes automatically every 10 seconds and briefly flashes when it changes.

### Traffic Sources Today

Shows the breakdown of today's traffic into four categories with counts and percentages:

- **Direct** – visitors who arrived via bookmark, by typing the URL directly, or from apps that don't send source information
- **Search engines** – traffic from Google, Bing, DuckDuckGo, and others
- **Social media** – traffic from Facebook, Instagram, X/Twitter, LinkedIn, Reddit, TikTok, and others
- **Other websites** – traffic via links on other sites

### Devices Today

Breakdown of today's visitors by device type – mobile, tablet, and desktop – with count and percentage. Device type is determined by the visitor's browser screen width.

### Avg. Time on Site

The average active time visitors spend on the site today. Calculated from heartbeat signals and only counts active periods – pauses where a tab was in the background are not included.

### Graph – Visitors Today

The line graph shows today's traffic hour by hour in 5-minute intervals from 00:00 to 23:55:

- **Blue line** – unique visitors today
- **Red dashed line** – pageviews today (may exceed visitors, since one person can read multiple articles)
- **Grey lines** – the same two metrics from **the same weekday last week** as a comparison baseline

Hovering over the graph shows a vertical line and a tooltip with all four values for that 5-minute interval, e.g. "08:40 – 08:45".

### Most Active Pages Right Now

The ten pages with the most active visitors in the current 120-second window. Click an article title to open it. Pages that are rising quickly in visitor counts are automatically marked with a 🔥 icon (trending).

**Trending:** A page is marked as trending if it has at least 3 active visitors right now and its visitor count has risen by at least 50% compared to the previous time window.

### Most Visited Pages Today

The fifteen pages with the most unique visitors throughout the entire day – not just in the most recent window.

### Bots Detected

Number of bots and crawlers detected today, along with a list of the most recently active bots by name and session count. Bots are filtered out from all other statistics so they don't skew the numbers.

### Top Referring Domains

Which specific domains (e.g. google.com, facebook.com) have sent the most visitors today. See the section on [traffic source detection](#traffic-source-detection) below for an explanation of how this is calculated.

---

## Settings

Find the settings under **WP VisitChart → Settings**.

### Admin Bar

Toggles the live visitor counter in WordPress' black admin bar at the top of the screen.

- **On:** A small number shows live visitors at the top, visible on all pages in wp-admin and on the site itself when logged in as an administrator. Click the number to go directly to the dashboard.
- **Off:** The counter is completely removed from the admin bar.

### Mobile Page

Toggles the login-free mobile page on or off.

- **On:** The mobile page is accessible via the secret link shown under "Mobile Page – Access" further down the settings page.
- **Off:** The mobile page shows an error message, even with the correct link.

### Pageviews in the Posts List

Toggles an extra column in WordPress' Posts and Pages list.

- **On:** A "Pageviews" column is shown with today's and total pageviews for each post. The column is sortable.
- **Off:** The column is hidden. Existing data in the database is preserved.

Pageviews are counted in real time via a separate JavaScript ping that fires exactly once per session per article – regardless of how short or long the visit is.

### Exclude Logged-in Users

Toggles whether logged-in WordPress users are excluded from all tracking.

- **On:** Heartbeat and pageview pings are completely stopped for logged-in users. Their visits will not appear in live counts, graphs, or pageview totals.
- **Off:** Logged-in users are tracked just like any other visitor.

Recommended for editorial sites, so editors' and authors' own page visits don't count towards traffic statistics.

### Mobile Page – Access

Here you'll find the link to the mobile page with the secret access code in the URL, along with a button to **reset the access code**. Clicking "Reset access code" generates a new code immediately, and the old link stops working. Use this if you've shared the link too widely or suspect misuse.

---

## The Mobile Page

The mobile page is a standalone, lightweight page you can bookmark on your phone or add to your home screen – without logging in to WordPress.

**Link format:**
```
https://yoursite.com/?lstats_mobile=YOUR-SECRET-CODE
```

Find the link under **WP VisitChart → Settings → Mobile Page – Access**.

The mobile page shows the same information as the dashboard, but adapted for a small screen. The live visitor count is pinned to the top and remains visible even when you scroll down through the lists.

**Security:** The page is not protected by login, but by the secret code in the URL. Do not share the link publicly.

---

## The Admin Bar

When enabled in settings, a small number with a graph icon appears in WordPress' black admin bar. It shows the same number as "Live Visitors Right Now" on the dashboard and updates every 10 seconds.

The admin bar counter is visible:
- On all pages in wp-admin
- On the site itself, when logged in as an administrator

Clicking the counter takes you directly to the dashboard.

---

## Pageviews in the Posts List

When enabled in settings, a **"Pageviews"** column appears in WordPress' Posts and Pages list.

The column shows:
- **Large number** – total pageviews for this post (including today)
- **"today: X"** – pageviews today only

Click the **"Pageviews"** column header to sort the entire list by popularity.

**Note:** The column must be enabled in WordPress' Screen Options (top right on the list page) to be visible. Check "Pageviews" in the list of available columns.

### How Pageviews Are Counted

Pageviews use a separate JavaScript ping that is independent of the heartbeat system:

- The ping fires exactly **once per session per article**
- `sessionStorage` ensures that reloading or navigating away and back does not double-count
- Bots and crawlers are filtered out via user-agent check on the server side
- No cron jobs – everything is updated directly in the database on each visit

**Daily rollover:** The first visit to an article after midnight automatically moves the previous day's "today" count into the total and starts fresh. No manual action or scheduled task is required.

---

## How Data is Collected

WP VisitChart uses three methods to collect traffic data:

### 1. Heartbeat (JavaScript)
A small script runs in the background on all pages and sends a signal every 12 seconds, as long as the visitor has the tab open and active. Automatically pauses when the tab is hidden (visitor switches to another tab). This is the source for the live count, the graph, and the active pages list.

### 2. Pageview Ping (JavaScript)
A separate, single signal that is only sent once, the first time a visitor opens a given article in a session. Used exclusively for the pageview column in the posts list.

### 3. Server-side Logging
Every page request is logged directly by the server, regardless of whether JavaScript is running. Catches visitors and bots that the heartbeat script never sees.

### Traffic Source Detection

Source detection happens in this order:

1. **`fbclid` parameter in the URL** – Facebook links always contain this parameter, even when the browser doesn't send referrer information. Provides reliable Facebook detection.
2. **`utm_source` parameter** – voluntary UTM tags in links, e.g. from newsletters or social campaigns.
3. **The referrer header** – the browser's information about which page the visitor came from.
4. **None of the above** – counted as Direct.

The result is categorised as Search Engines, Social Media, Other Websites, or Direct.

### Data Retention

Raw heartbeat data is stored for **8 days** and automatically purged afterwards. 8 days is necessary to support the comparison with "the same weekday last week" in the graph.

Pageview data in the `lstats_post_views` table is retained indefinitely and is not automatically purged.

---

## What the Numbers Mean

| Metric | What it measures | Update frequency |
|---|---|---|
| Live visitors | Unique sessions with heartbeat in the last 120 sec. | Every 10 sec. |
| Traffic sources today | Unique sessions broken down by source | Every 60 sec. |
| Devices today | Unique sessions broken down by screen width | Every 60 sec. |
| Avg. time on site | Active time based on heartbeat intervals | Every 60 sec. |
| Graph – today | Unique sessions per 5-minute interval | Every 60 sec. |
| Graph – last week | Same calculation, 7 days back | Every 60 sec. |
| Pageviews (column) | Session-unique page loads per article | Real-time |
| Bots | Registered bot sessions today | Every 60 sec. |

---

## Known Limitations

**"Direct" traffic is often overstated.** Many browsers and apps don't send referrer information for privacy reasons, even when a visitor clicked a link. These are counted as Direct, even though they may have come from somewhere else.

**The live count and the graph don't measure the same thing.** The live count uses a rolling 120-second window. The graph uses 5-minute buckets. The two numbers are not directly comparable.

**Average time on site is an approximation.** Calculated from gaps between heartbeats. Visits shorter than 12 seconds are not registered as active time. Tab pauses longer than 60 seconds are not counted.

**WP Cron is not a real system cron.** Heartbeat cleanup and other scheduled tasks are only triggered when someone visits the site. On a site with low overnight traffic, a scheduled task may be delayed by an hour or more before it actually runs.

**Bot detection is not 100% complete.** Advanced bots that impersonate regular browsers may slip through the filter and be counted as real visitors.

**The pageview ping requires JavaScript.** Visitors without JavaScript, bots, and crawlers are not registered as pageviews in the column, as the ping is JavaScript-based.

---

*WP VisitChart 1.9.3 – Copyright © 2026 Jens E. Hummelmose*
