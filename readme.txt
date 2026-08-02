=== WP VisitChart ===
Contributors: hummelmose
Tags: analytics, live visitors, statistics, pageviews, chartbeat
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.6.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted, Chartbeat-inspired live analytics for WordPress. Track live visitors, pageviews, traffic sources, and trending articles in real time.

== Description ==

WP VisitChart gives you real-time editorial analytics without sending data to any third-party service. All data stays in your own database.

= Features =

* Live visitor count updating every 10 seconds
* Traffic graph in 5-minute intervals compared to the same weekday last week
* Most Active Pages Right Now with trending 🔥 and featured ⭐ badges
* Most Visited Today — top 20 articles by pageviews since midnight
* Traffic source breakdown (Direct, Search, Social, Other) with UTM and fbclid detection
* Device breakdown (Mobile, Tablet, Desktop)
* Bot detection and listing
* Top 15 referring domains
* Bookmarkable mobile page with token-based access — no login required
* Live visitor count in the WordPress admin bar
* Sortable pageviews column in the Posts and Pages list
* WordPress dashboard widget showing Top 20 articles today
* Light and dark mode — preference saved per user (admin) or in localStorage (mobile)
* Exclude logged-in users from all tracking
* Featured category — mark a category whose articles get a ⭐ badge
* WP Rocket and Redis compatible

= Privacy =

WP VisitChart does not use cookies and does not send any data to external services. All data is stored exclusively in your WordPress database and automatically deleted after 8 days (except pageview totals).

== Installation ==

1. Download the plugin zip file
2. In WordPress admin go to **Plugins → Add Plugin → Upload Plugin**
3. Upload the zip file and activate the plugin
4. Go to **WP VisitChart** in the left admin menu

== Frequently Asked Questions ==

= Why is my live count higher than Google Analytics? =

Live counters and traditional analytics measure different things. WP VisitChart counts unique browser sessions active in the last 120 seconds. A session expires when a browser tab is closed. Traditional tools use cookies lasting 30+ minutes, collapsing many sessions into one visit. A 1.5–2× difference is normal.

= Does this work without WP Rocket? =

Yes. WP Rocket hooks only fire if WP Rocket is installed.

= Does it use cookies? =

No. Session IDs are stored in the browser's sessionStorage, which clears when the tab is closed.

== Changelog ==

= 2.6.5 =
* Code quality: replaced date() with gmdate() throughout
* Code quality: added GPL-2.0-or-later license header
* Code quality: added wp_unslash() where missing
* Code quality: added esc_attr/esc_html where missing
* Code quality: removed deprecated load_plugin_textdomain() call

= 2.6.4 =
* Fix: missing left/right padding on admin dashboard after heading removal

= 2.6.3 =
* Fix: missing top margin on admin dashboard after heading removal

= 2.6.0 =
* Fix: sticky live bar not working on Chrome for Android

= 2.5.8 =
* Fix: white gaps between cards in dark mode

= 2.5.6 =
* New: light/dark mode toggle on admin dashboard and mobile page

= 2.5.4 =
* New: WordPress dashboard widget showing Top 20 articles today

= 2.4.6 =
* New: Featured category setting with star badge on article lists

= 2.3.0 =
* Performance: new sessions table reduces referrer query from 1.4M rows to 25k

= 2.1.0 =
* Performance: covering index on heartbeats table, SQL aggregation for graph

= 2.0.0 =
* New: sticky live bar, full-width graph, redesigned two-column layout

= 1.9.3 =
* Initial public release

== Upgrade Notice ==

= 2.6.5 =
Code quality improvements. No database changes required.
