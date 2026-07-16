<?php
/**
 * Plugin Name: WP VisitChart
 * Description: Viser live besøgende og dagens trafikhistorik for WordPress.
 * Version: 2.1.2
 * Author: Jens E. Hummelmose
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Text Domain: wp-visitchart
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LSTATS_TABLE', 'lstats_heartbeats' );
define( 'LSTATS_VIEWS_TABLE', 'lstats_post_views' );
define( 'LSTATS_DB_VERSION', '1.7' );

/**
 * Indlæs oversættelser fra /languages-mappen
 */
function lstats_load_textdomain() {
    load_plugin_textdomain( 'wp-visitchart', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'lstats_load_textdomain' );

/**
 * Tjekker om Font Awesome allerede er indlæst et andet sted på sitet (af temaet
 * eller et andet plugin), så vi undgår at indlæse det dobbelt. Vi trigger
 * registrerings-hooket selv, hvis det ikke allerede er sket, for at sikre at
 * andre styles er nået at blive registreret, før vi kigger efter dem.
 */
function lstats_is_fontawesome_loaded( $context = 'frontend' ) {
    global $wp_styles;

    // I admin-kontekst er vi allerede inde i selve admin_enqueue_scripts-hooket,
    // så vi kan ikke trigge det igen (det ville give en uendelig løkke). Her
    // stoler vi i stedet på, at vores eget hook kører sent (lav prioritet),
    // så andre plugins/temaer allerede har nået at sætte deres styles i kø.
    if ( 'frontend' === $context && ! did_action( 'wp_enqueue_scripts' ) ) {
        do_action( 'wp_enqueue_scripts' );
    }

    if ( empty( $wp_styles ) ) {
        return false;
    }

    // Vigtigt: vi tjekker $wp_styles->queue (det der faktisk bliver vist på
    // DENNE side), ikke ->registered. Mange plugins registrerer Font Awesome
    // globalt på alle admin-sider, men sætter det kun i kø (enqueue) på deres
    // egne specifikke sider - et tjek mod ->registered ville derfor fejlagtigt
    // tro, at Font Awesome er indlæst, selvom intet reelt vises på vores side.
    if ( empty( $wp_styles->queue ) ) {
        return false;
    }

    foreach ( $wp_styles->queue as $handle ) {
        if ( false !== stripos( $handle, 'font-awesome' ) || false !== stripos( $handle, 'fontawesome' ) ) {
            return true;
        }
        if ( isset( $wp_styles->registered[ $handle ] ) && ! empty( $wp_styles->registered[ $handle ]->src ) ) {
            $src = $wp_styles->registered[ $handle ]->src;
            if ( false !== stripos( $src, 'font-awesome' ) || false !== stripos( $src, 'fontawesome' ) ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Opret database-tabel ved aktivering
 */
function lstats_activate() {
    lstats_create_or_upgrade_table();
    lstats_maybe_generate_public_token();
}
register_activation_hook( __FILE__, 'lstats_activate' );

/**
 * Hjælpefunktioner til at læse plugin-indstillinger. Bruger get_option med
 * fornuftige standardværdier, så alt fortsætter at virke som hidtil, selv på
 * sites der opgraderer fra en version uden indstillingssiden.
 */
function lstats_is_admin_bar_enabled() {
    return '0' !== get_option( 'lstats_admin_bar_enabled', '1' );
}

function lstats_is_mobile_site_enabled() {
    return '0' !== get_option( 'lstats_mobile_enabled', '1' );
}

function lstats_is_post_views_enabled() {
    $val = get_option( 'lstats_post_views_enabled', '0' );
    return ! empty( $val ) && '0' !== $val;
}

function lstats_exclude_logged_in_users() {
    return '1' === get_option( 'lstats_exclude_logged_in', '0' );
}

/**
 * Generer en hemmelig token til den offentlige mobil-side, hvis den ikke allerede findes
 */
function lstats_maybe_generate_public_token() {
    if ( ! get_option( 'lstats_public_token' ) ) {
        update_option( 'lstats_public_token', wp_generate_password( 32, false, false ) );
    }
}
add_action( 'plugins_loaded', 'lstats_maybe_generate_public_token' );

function lstats_create_or_upgrade_table() {
    global $wpdb;
    $table       = $wpdb->prefix . LSTATS_TABLE;
    $views_table = $wpdb->prefix . LSTATS_VIEWS_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        session_id VARCHAR(64) NOT NULL,
        url VARCHAR(500) NOT NULL,
        is_bot TINYINT(1) NOT NULL DEFAULT 0,
        source VARCHAR(20) NOT NULL DEFAULT 'heartbeat',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        referrer VARCHAR(255) NOT NULL DEFAULT '',
        device_type VARCHAR(20) NOT NULL DEFAULT '',
        country VARCHAR(2) NOT NULL DEFAULT '',
        city VARCHAR(100) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at),
        KEY idx_post_id (post_id),
        KEY idx_session (session_id),
        KEY idx_bot_source_time (is_bot, source, created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Letvægts tabel til sidevisninger per indlæg/side. Én række per post,
    // med akkumulerede tællere der opdateres af et timecron-job. Adskilt fra
    // rådata i heartbeat-tabellen, så forespørgsler i posts-oversigten forbliver hurtige.
    $views_sql = "CREATE TABLE $views_table (
        post_id BIGINT UNSIGNED NOT NULL,
        views_today INT UNSIGNED NOT NULL DEFAULT 0,
        views_7days INT UNSIGNED NOT NULL DEFAULT 0,
        views_total INT UNSIGNED NOT NULL DEFAULT 0,
        count_date DATE NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (post_id),
        KEY idx_views_total (views_total)
    ) $charset_collate;";

    dbDelta( $views_sql );

    update_option( 'lstats_db_version', LSTATS_DB_VERSION );
}

/**
 * Tjek ved hvert load om tabellen skal opgraderes (f.eks. efter plugin-opdatering)
 */
function lstats_maybe_upgrade() {
    if ( get_option( 'lstats_db_version' ) !== LSTATS_DB_VERSION ) {
        lstats_create_or_upgrade_table();
    }
}
add_action( 'plugins_loaded', 'lstats_maybe_upgrade' );

/**
 * Simpelt bot-tjek baseret på user-agent streng
 */
/**
 * Fælles bot-signatur-array brugt af både lstats_is_bot_user_agent og lstats_get_bot_name.
 * Bygges én gang per request via static og genbruges derefter, så vi undgår at
 * genallokere arrayet ved hvert enkelt kald. Rækkefølgen er vigtig: mere specifikke
 * signaturer (f.eks. 'googlebot') står før generiske ('bot') så vi får det rigtige navn.
 */
function lstats_bot_signatures() {
    static $sigs = null;
    if ( null === $sigs ) {
        $sigs = array(
            'googlebot'           => 'Googlebot',
            'bingbot'             => 'Bingbot',
            'bingpreview'         => 'Bing Preview',
            'duckduckbot'         => 'DuckDuckBot',
            'yandexbot'           => 'YandexBot',
            'yandex'              => 'YandexBot',
            'baiduspider'         => 'Baidu Spider',
            'facebookexternalhit' => 'Facebook',
            'slurp'               => 'Yahoo Slurp',
            'ahrefsbot'           => 'AhrefsBot',
            'semrushbot'          => 'SemrushBot',
            'mj12bot'             => 'Majestic (MJ12bot)',
            'dotbot'              => 'DotBot',
            'petalbot'            => 'PetalBot',
            'gptbot'              => 'GPTBot (OpenAI)',
            'oai-searchbot'       => 'OpenAI SearchBot',
            'chatgpt-user'        => 'ChatGPT-User',
            'claudebot'           => 'ClaudeBot (Anthropic)',
            'claude-web'          => 'Claude-Web (Anthropic)',
            'anthropic'           => 'Anthropic-bot',
            'ccbot'               => 'CCBot (Common Crawl)',
            'perplexitybot'       => 'PerplexityBot',
            'amazonbot'           => 'Amazonbot',
            'applebot'            => 'Applebot',
            'bytespider'          => 'ByteSpider (TikTok)',
            'linkedinbot'         => 'LinkedInBot',
            'twitterbot'          => 'Twitterbot',
            'pinterest'           => 'Pinterest',
            'wp-rocket'           => 'WP Rocket Preload',
            'rocket-preload'      => 'WP Rocket Preload',
            'wprocketbot'         => 'WP Rocket Preload',
            'cloudflare'          => 'Cloudflare',
            'cf-preload'          => 'Cloudflare Preload',
            'pingdom'             => 'Pingdom Monitor',
            'uptime'              => 'Uptime Monitor',
            'monitor'             => 'Uptime Monitor',
            'lighthouse'          => 'Google Lighthouse',
            'pagespeed'           => 'PageSpeed Insights',
            'gtmetrix'            => 'GTmetrix',
            'curl'                => 'curl-script',
            'wget'                => 'wget-script',
            'python-requests'     => 'Python-script',
            'headlesschrome'      => 'Headless Chrome',
            'phantomjs'           => 'PhantomJS',
            'bot'                 => 'Bot',
            'crawl'               => 'Crawler',
            'spider'              => 'Spider',
        );
    }
    return $sigs;
}

function lstats_is_bot_user_agent( $user_agent ) {
    if ( empty( $user_agent ) ) {
        return true;
    }
    $ua_lower = strtolower( $user_agent );
    foreach ( lstats_bot_signatures() as $signature => $_ ) {
        if ( false !== strpos( $ua_lower, $signature ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Udleder et læsbart bot-navn fra user-agent strengen
 */
function lstats_get_bot_name( $user_agent ) {
    if ( empty( $user_agent ) ) {
        return 'Ukendt bot (ingen user-agent)';
    }
    $ua_lower = strtolower( $user_agent );
    foreach ( lstats_bot_signatures() as $signature => $name ) {
        if ( false !== strpos( $ua_lower, $signature ) ) {
            return $name;
        }
    }
    return 'Anden bot';
}

/**
 * Kategoriserer en referrer-URL i: direkte, søgning, sociale medier eller andre sites,
 * og udleder det rene domænenavn til visning
 */
function lstats_categorize_referrer( $referrer, $url = '' ) {
    // fbclid og utm_source tjekkes altid først, uanset om der er en referrer eller ikke.
    // fbclid er Facebooks egen klik-tracking-parameter, som altid tilføjes til links,
    // der klikkes på inde fra Facebook (app eller web) - selv når referrer mangler helt,
    // hvilket er den mest almindelige situation med Facebooks app-browser.
    if ( ! empty( $url ) ) {
        $query = wp_parse_url( $url, PHP_URL_QUERY );
        if ( $query ) {
            parse_str( $query, $params );

            if ( ! empty( $params['fbclid'] ) ) {
                return array(
                    'category' => 'social',
                    'domain'   => 'facebook.com (fbclid)',
                );
            }

            if ( ! empty( $params['utm_source'] ) ) {
                $source = strtolower( sanitize_text_field( $params['utm_source'] ) );
                return lstats_categorize_utm_source( $source );
            }
        }
    }

    if ( empty( $referrer ) ) {
        return array(
            'category' => 'direct',
            'domain'   => '',
        );
    }

    $host = wp_parse_url( $referrer, PHP_URL_HOST );
    if ( empty( $host ) ) {
        return array(
            'category' => 'direct',
            'domain'   => '',
        );
    }

    $host = preg_replace( '/^www\./', '', strtolower( $host ) );

    // home_url() slår op i databasen (get_option). Med static cache kaldes det
    // kun én gang per request, selv når funktionen kaldes 25.000 gange i en løkke.
    static $site_host = null;
    if ( null === $site_host ) {
        $site_host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
    }

    if ( $host === $site_host ) {
        return array(
            'category' => 'direct',
            'domain'   => '',
        );
    }

    $search_engines = array(
        'google.', 'bing.com', 'yahoo.', 'duckduckgo.com', 'ecosia.org',
        'baidu.com', 'yandex.', 'startpage.com', 'qwant.com', 'sogou.com',
        'ask.com', 'aol.com', 'naver.com',
    );

    $social_networks = array(
        'facebook.com', 'fb.com', 'instagram.com', 'twitter.com', 'x.com',
        't.co', 'linkedin.com', 'pinterest.', 'reddit.com', 'tiktok.com',
        'youtube.com', 'snapchat.com', 'threads.net', 'mastodon.', 'whatsapp.com',
        'messenger.com', 'discord.com', 'telegram.org', 'lemmy.',
    );

    foreach ( $search_engines as $signature ) {
        if ( false !== strpos( $host, $signature ) ) {
            return array(
                'category' => 'search',
                'domain'   => $host,
            );
        }
    }

    foreach ( $social_networks as $signature ) {
        if ( false !== strpos( $host, $signature ) ) {
            return array(
                'category' => 'social',
                'domain'   => $host,
            );
        }
    }

    return array(
        'category' => 'other',
        'domain'   => $host,
    );
}

/**
 * Kategoriserer en utm_source-værdi (brugt som fallback, når referrer mangler)
 */
function lstats_categorize_utm_source( $source ) {
    $search_sources = array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex' );
    $social_sources = array(
        'facebook', 'fb', 'instagram', 'twitter', 'x', 'linkedin', 'pinterest',
        'reddit', 'tiktok', 'youtube', 'snapchat', 'threads', 'whatsapp',
        'messenger', 'telegram',
    );

    if ( in_array( $source, $search_sources, true ) ) {
        return array(
            'category' => 'search',
            'domain'   => $source . ' (utm)',
        );
    }

    if ( in_array( $source, $social_sources, true ) ) {
        return array(
            'category' => 'social',
            'domain'   => $source . ' (utm)',
        );
    }

    return array(
        'category' => 'other',
        'domain'   => $source . ' (utm)',
    );
}

/**
 * Gætter enhedstype (mobil/tablet/desktop) ud fra user-agent. Bruges som fallback
 * for besøgende, der ikke kører JavaScript (fx bots, eller server-side pageload-log)
 */
function lstats_guess_device_type( $user_agent ) {
    if ( empty( $user_agent ) ) {
        return 'desktop';
    }
    $ua = strtolower( $user_agent );

    if ( preg_match( '/ipad|tablet|playbook|silk/', $ua ) && ! preg_match( '/mobile/', $ua ) ) {
        return 'tablet';
    }
    if ( preg_match( '/mobi|android|iphone|ipod|blackberry|opera mini|iemobile/', $ua ) ) {
        return 'mobile';
    }
    return 'desktop';
}

/**
 * Indlæs heartbeat-script på frontend
 */
function lstats_enqueue_frontend() {
    if ( is_admin() ) {
        return;
    }

    wp_enqueue_script(
        'lstats-heartbeat',
        plugins_url( 'heartbeat.js', __FILE__ ),
        array(),
        '1.0',
        true
    );

    global $post;
    $post_id = $post ? $post->ID : 0;

    wp_localize_script( 'lstats-heartbeat', 'lstatsData', array(
        'restUrl'        => esc_url_raw( rest_url( 'lstats/v1/heartbeat' ) ),
        'pageviewUrl'    => esc_url_raw( rest_url( 'lstats/v1/pageview' ) ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'postId'         => $post_id,
        'url'            => esc_url_raw( $_SERVER['REQUEST_URI'] ),
        'isLoggedIn'     => is_user_logged_in() ? '1' : '0',
        'excludeLoggedIn' => lstats_exclude_logged_in_users() ? '1' : '0',
    ) );
}
add_action( 'wp_enqueue_scripts', 'lstats_enqueue_frontend' );

/**
 * Log hver sidevisning på serverniveau, uafhængigt af JavaScript.
 * Dette fanger almindelige bots/crawlere, som heartbeat-scriptet aldrig ser,
 * fordi de ikke udfører JavaScript.
 */
function lstats_log_pageload() {
    if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) || isset( $_GET['lstats_mobile'] ) ) {
        return;
    }

    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    $is_bot     = lstats_is_bot_user_agent( $user_agent );

    // Rækker med source='pageload' og is_bot=0 bruges aldrig i nogen queries –
    // alle brugerdata hentes udelukkende fra heartbeat-rækker. Bots kører normalt
    // ikke JavaScript og sender dermed ikke heartbeats, så de skrives stadig her.
    // Ved at springe non-bot INSERTs over reducerer vi skrivningerne markant
    // (typisk 99%+ af sideindlæsninger er ikke-bots) og holder tabellen slank.
    if ( ! $is_bot ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $post_id  = get_queried_object_id();
    $url      = sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' );
    $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

    $session_id = 'srv_' . substr( md5( $ip . $user_agent ), 0, 20 );
    $device     = lstats_guess_device_type( $user_agent );

    $wpdb->insert(
        $table,
        array(
            'post_id'     => $post_id,
            'session_id'  => $session_id,
            'url'         => $url,
            'is_bot'      => 1,
            'source'      => 'pageload',
            'user_agent'  => $user_agent,
            'referrer'    => $referrer,
            'device_type' => $device,
            'created_at'  => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
    );
}
add_action( 'template_redirect', 'lstats_log_pageload' );

/**
 * Viser en simpel, login-fri mobil-side med live-stats, hvis korrekt token er angivet i URL'en.
 * Tilgås via: https://dinside.dk/?lstats_mobile=TOKEN
 */
function lstats_maybe_render_mobile_view() {
    if ( ! isset( $_GET['lstats_mobile'] ) ) {
        return;
    }

    if ( ! lstats_is_mobile_site_enabled() ) {
        wp_die( esc_html__( 'Mobilsiden er deaktiveret.', 'wp-visitchart' ), '', array( 'response' => 403 ) );
    }

    $token = sanitize_text_field( wp_unslash( $_GET['lstats_mobile'] ) );
    $stored_token = get_option( 'lstats_public_token' );

    if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
        wp_die( esc_html__( 'Forkert eller manglende adgangskode.', 'wp-visitchart' ), '', array( 'response' => 403 ) );
    }

    lstats_render_mobile_page( $token );
    exit;
}
add_action( 'template_redirect', 'lstats_maybe_render_mobile_view', 1 );

function lstats_render_mobile_page( $token ) {
    $rest_url = esc_url_raw( rest_url( 'lstats/v1/' ) );
    ?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php esc_html_e( 'WP VisitChart - Mobil', 'wp-visitchart' ); ?></title>
    <?php
    // Denne side er fuldstændig isoleret fra temaet (vi kalder aldrig wp_head()),
    // så vi indlæser altid vores egen Font Awesome her. Et "er den allerede
    // indlæst"-tjek ville give falsk tryghed, da temaets egen version aldrig
    // reelt bliver skrevet ud på denne side, uanset hvad tjekket finder.
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( plugins_url( 'icons/icon-32.png', __FILE__ ) ); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( plugins_url( 'icons/icon-16.png', __FILE__ ) ); ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( plugins_url( 'icons/icon-192.png', __FILE__ ) ); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( plugins_url( 'icons/icon-180.png', __FILE__ ) ); ?>">
    <link rel="manifest" href="data:application/json,<?php echo rawurlencode( wp_json_encode( array(
        'name'             => 'WP VisitChart',
        'short_name'       => 'VisitChart',
        'display'          => 'standalone',
        'start_url'        => add_query_arg( 'lstats_mobile', $token, home_url( '/' ) ),
        'background_color' => '#f0f0f1',
        'theme_color'      => '#2271b1',
        'icons'            => array(
            array(
                'src'   => plugins_url( 'icons/icon-192.png', __FILE__ ),
                'sizes' => '192x192',
                'type'  => 'image/png',
            ),
            array(
                'src'   => plugins_url( 'icons/icon-512.png', __FILE__ ),
                'sizes' => '512x512',
                'type'  => 'image/png',
            ),
        ),
    ) ) ); ?>">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            padding-top: 90px;
            background: #f0f0f1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1d2327;
        }
        .sticky-live-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid #dcdcde;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 10px 16px;
            text-align: center;
        }
        .sticky-live-bar .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #646970;
            margin-bottom: 2px;
        }
        .sticky-number {
            font-size: 30px;
            font-weight: 700;
            color: #2271b1;
            line-height: 1.1;
            transition: color 0.2s ease;
        }
        .sticky-number.flash {
            color: #72aee6;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 16px;
        }
        .card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #646970;
            margin-bottom: 12px;
        }
        .number {
            font-size: 56px;
            font-weight: 700;
            color: #2271b1;
            text-align: center;
            line-height: 1.3;
            margin-top: 8px;
            margin-bottom: 10px;
            transition: color 0.2s ease;
        }
        .number.flash {
            color: #72aee6;
        }
        .bot-count {
            font-size: 12px;
            color: #8c8f94;
            text-align: center;
            margin-bottom: 10px;
        }
        .sublabel {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #8c8f94;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f1;
        }
        .bot-list, .page-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .bot-list li, .page-list li {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 7px 0;
            border-bottom: 1px solid #f0f0f1;
            font-size: 14px;
        }
        .bot-list li:last-child, .page-list li:last-child {
            border-bottom: none;
        }
        .page-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }
        .page-title a {
            color: #1d2327;
            text-decoration: none;
        }
        .count-num {
            font-weight: 600;
            color: #2271b1;
            flex-shrink: 0;
        }
        .value-wrap {
            display: flex;
            justify-content: flex-end;
            flex-shrink: 0;
        }
        .value-wrap .count-num {
            min-width: 44px;
            text-align: right;
        }
        .count-pct {
            min-width: 54px;
            text-align: right;
            color: #8c8f94;
            font-weight: 400;
            font-size: 12px;
        }
        .chart-wrap {
            height: 280px;
            max-height: 280px;
            position: relative;
        }
        .empty {
            color: #8c8f94;
            font-style: italic;
            font-size: 14px;
        }
        .trending-badge {
            flex-shrink: 0;
            color: #d63638;
            margin: 0 4px;
        }
        .refresh-note {
            text-align: center;
            font-size: 11px;
            color: #8c8f94;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="sticky-live-bar">
        <div class="label"><?php esc_html_e( 'Live besøgende lige nu', 'wp-visitchart' ); ?></div>
        <div class="sticky-number" id="m-total">0</div>
    </div>

    <h1><?php esc_html_e( 'Live besøgende og dagens trafik', 'wp-visitchart' ); ?></h1>

    <div class="card">
        <div class="label"><?php esc_html_e( 'Besøgende i dag (5-minutters intervaller)', 'wp-visitchart' ); ?></div>
        <div class="chart-wrap">
            <canvas id="m-chart"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="label"><?php esc_html_e( 'Mest aktive sider lige nu', 'wp-visitchart' ); ?></div>
        <ul class="page-list" id="m-pages-list"></ul>
    </div>

    <div class="card">
        <div class="label"><?php esc_html_e( 'Mest besøgte sider i dag', 'wp-visitchart' ); ?></div>
        <ul class="page-list" id="m-top-pages-list"></ul>
    </div>

    <div class="card">
        <div class="label"><?php esc_html_e( 'Trafikkilder i dag', 'wp-visitchart' ); ?></div>
        <ul class="bot-list" id="m-referrer-categories-list"></ul>
    </div>

    <div class="card">
        <div class="label"><?php esc_html_e( 'Enheder i dag', 'wp-visitchart' ); ?></div>
        <ul class="bot-list" id="m-devices-list"></ul>
        <ul class="bot-list">
            <li><span class="page-title"><?php esc_html_e( 'Gns. tid på sitet', 'wp-visitchart' ); ?>:</span>
                <span class="count-num" id="m-avg-time"> 0:00</span></li>
        </ul>
    </div>

    <div class="card">
        <div class="bot-count" id="m-bot-total">0 <?php esc_html_e( 'bots registreret', 'wp-visitchart' ); ?></div>
        <ul class="bot-list" id="m-bot-list"></ul>
    </div>

    <div class="card">
        <div class="label"><?php esc_html_e( 'Mest henvisende domæner', 'wp-visitchart' ); ?></div>
        <ul class="page-list" id="m-referrer-domains-list"></ul>
    </div>

    <div class="refresh-note"><?php esc_html_e( 'Opdateres automatisk', 'wp-visitchart' ); ?></div>

    <p style="text-align: center; color: #8c8f94; font-size: 11px; margin-top: 24px; padding-bottom: 16px;">
        WP VisitChart 1.9.3 &mdash; Copyright &copy; 2026<?php $y = date('Y'); if ( $y > 2026 ) { echo '&ndash;' . esc_html( $y ); } ?> Jens E. Hummelmose
    </p>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script>
    (function () {
        'use strict';

        var restUrl = <?php echo wp_json_encode( $rest_url ); ?>;
        var token = <?php echo wp_json_encode( $token ); ?>;
        var locale = <?php echo wp_json_encode( str_replace( '_', '-', get_locale() ) ); ?>;
        var i18n = {
            uniqueVisitors: <?php echo wp_json_encode( __( 'Unikke besøgende', 'wp-visitchart' ) ); ?>,
            pageviews: <?php echo wp_json_encode( __( 'Sidevisninger', 'wp-visitchart' ) ); ?>,
            yesterday: <?php echo wp_json_encode( __( 'samme ugedag sidste uge', 'wp-visitchart' ) ); ?>,
            noActivePages: <?php echo wp_json_encode( __( 'Ingen aktive sider', 'wp-visitchart' ) ); ?>,
            noDataToday: <?php echo wp_json_encode( __( 'Ingen data endnu i dag', 'wp-visitchart' ) ); ?>,
            botsRegistered: <?php echo wp_json_encode( __( 'bots registreret', 'wp-visitchart' ) ); ?>,
            direct: <?php echo wp_json_encode( __( 'Direkte', 'wp-visitchart' ) ); ?>,
            search: <?php echo wp_json_encode( __( 'Søgemaskiner', 'wp-visitchart' ) ); ?>,
            social: <?php echo wp_json_encode( __( 'Sociale medier', 'wp-visitchart' ) ); ?>,
            other: <?php echo wp_json_encode( __( 'Andre hjemmesider', 'wp-visitchart' ) ); ?>,
            noReferrers: <?php echo wp_json_encode( __( 'Ingen henvisende domæner i dag', 'wp-visitchart' ) ); ?>,
            mobile: <?php echo wp_json_encode( __( 'Mobil', 'wp-visitchart' ) ); ?>,
            tablet: <?php echo wp_json_encode( __( 'Tablet', 'wp-visitchart' ) ); ?>,
            desktop: <?php echo wp_json_encode( __( 'Desktop', 'wp-visitchart' ) ); ?>,
            trending: <?php echo wp_json_encode( __( 'Trending', 'wp-visitchart' ) ); ?>
        };

        function formatNumber(n) {
            try {
                return new Intl.NumberFormat(locale || 'da-DK').format(n);
            } catch (e) {
                return n;
            }
        }

        var chartInstance = null;
        var previousTotal = null;

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function fetchJson(endpoint) {
            return fetch(restUrl + endpoint + '?token=' + encodeURIComponent(token))
                .then(function (res) { return res.json(); });
        }

        function updateLiveCount() {
            fetchJson('live-count').then(function (data) {
                var totalEl = document.getElementById('m-total');
                totalEl.textContent = formatNumber(data.total);

                if (previousTotal !== null && data.total !== previousTotal) {
                    totalEl.classList.remove('flash');
                    void totalEl.offsetWidth;
                    totalEl.classList.add('flash');
                    setTimeout(function () { totalEl.classList.remove('flash'); }, 600);
                }
                previousTotal = data.total;

                document.getElementById('m-bot-total').textContent = data.bot_total + ' ' + i18n.botsRegistered;

                var botList = document.getElementById('m-bot-list');
                botList.innerHTML = '';
                if (data.bots && data.bots.length > 0) {
                    data.bots.forEach(function (bot) {
                        var li = document.createElement('li');
                        li.innerHTML = '<span class="page-title">' + escapeHtml(bot.name) + '</span>' +
                                        '<span class="count-num">' + formatNumber(bot.count) + '</span>';
                        botList.appendChild(li);
                    });
                }

                var list = document.getElementById('m-pages-list');
                list.innerHTML = '';
                if (!data.pages || data.pages.length === 0) {
                    list.innerHTML = '<li class="empty">' + escapeHtml(i18n.noActivePages) + '</li>';
                } else {
                    data.pages.forEach(function (page, index) {
                        var li = document.createElement('li');
                        var num = String(index + 1).padStart(2, '0');
                        var badge = page.trending ? '<span class="trending-badge" title="' + escapeHtml(i18n.trending) + '"><i class="fa-solid fa-fire"></i></span>' : '';
                        li.innerHTML = '<span class="page-title"><b>' + num + ':</b> ' +
                                        '<a href="' + escapeHtml(page.url) + '">' + escapeHtml(page.title) + '</a></span>' +
                                        badge +
                                        '<span class="count-num">' + formatNumber(page.live) + '</span>';
                        list.appendChild(li);
                    });
                }
            });
        }

        function formatTimeRange(label) {
            var parts = label.split(':');
            var h = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) + 5;
            if (m >= 60) {
                m -= 60;
                h = (h + 1) % 24;
            }
            var endLabel = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            return label + ' - ' + endLabel;
        }

        var verticalLinePlugin = {
            id: 'lstatsVerticalLine',
            afterDraw: function (chart) {
                if (chart.tooltip && chart.tooltip._active && chart.tooltip._active.length) {
                    var ctx = chart.ctx;
                    var x = chart.tooltip._active[0].element.x;
                    var topY = chart.scales.y.top;
                    var bottomY = chart.scales.y.bottom;
                    ctx.save();
                    ctx.beginPath();
                    ctx.moveTo(x, topY);
                    ctx.lineTo(x, bottomY);
                    ctx.lineWidth = 1;
                    ctx.setLineDash([4, 4]);
                    ctx.strokeStyle = 'rgba(34, 113, 177, 0.7)';
                    ctx.stroke();
                    ctx.restore();
                }
            },
        };

        function updateHistoryChart() {
            fetchJson('today-history').then(function (data) {
                var labels = data.map(function (d) { return d.time; });
                var counts = data.map(function (d) { return d.count; });
                var pageviews = data.map(function (d) { return d.pageviews; });
                var prevCounts = data.map(function (d) { return d.prevCount; });
                var prevPageviews = data.map(function (d) { return d.prevPageviews; });

                var ctx = document.getElementById('m-chart').getContext('2d');

                if (chartInstance) {
                    chartInstance.data.labels = labels;
                    chartInstance.data.datasets[0].data = counts;
                    chartInstance.data.datasets[1].data = pageviews;
                    chartInstance.data.datasets[2].data = prevCounts;
                    chartInstance.data.datasets[3].data = prevPageviews;
                    chartInstance.update();
                    return;
                }

                chartInstance = new Chart(ctx, {
                    type: 'line',
                    plugins: [verticalLinePlugin],
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: i18n.uniqueVisitors,
                                data: counts,
                                borderColor: '#2271b1',
                                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2, order: 2,
                            },
                            {
                                label: i18n.pageviews,
                                data: pageviews,
                                borderColor: '#d63638',
                                backgroundColor: 'rgba(214, 54, 56, 0.05)',
                                fill: false, tension: 0.3, pointRadius: 0, borderWidth: 2, borderDash: [4, 4], order: 2,
                            },
                            {
                                label: i18n.uniqueVisitors + ' (' + i18n.yesterday + ')',
                                data: prevCounts,
                                borderColor: 'rgba(150, 150, 150, 0.5)',
                                backgroundColor: 'rgba(150, 150, 150, 0.08)',
                                fill: true, tension: 0.3, pointRadius: 0, borderWidth: 1, order: 1,
                            },
                            {
                                label: i18n.pageviews + ' (' + i18n.yesterday + ')',
                                data: prevPageviews,
                                borderColor: 'rgba(150, 150, 150, 0.35)',
                                backgroundColor: 'rgba(0,0,0,0)',
                                fill: false, tension: 0.3, pointRadius: 0, borderWidth: 1, borderDash: [3, 3], order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 10 } } },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                itemSort: function(a, b) {
                                    return a.datasetIndex - b.datasetIndex;
                                },
                                callbacks: {
                                    title: function(tooltipItems) {
                                        return formatTimeRange(tooltipItems[0].label);
                                    },
                                },
                            },
                        },
                        hover: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            x: { ticks: { maxTicksLimit: 6, font: { size: 9 } } },
                            y: { beginAtZero: true, ticks: { precision: 0 } },
                        },
                    },
                });
            });
        }

        function updateTopPages() {
            fetchJson('top-pages').then(function (data) {
                var list = document.getElementById('m-top-pages-list');
                list.innerHTML = '';
                if (!data || data.length === 0) {
                    list.innerHTML = '<li class="empty">' + escapeHtml(i18n.noDataToday) + '</li>';
                    return;
                }
                data.forEach(function (page, index) {
                    var li = document.createElement('li');
                    var num = String(index + 1).padStart(2, '0');
                    li.innerHTML = '<span class="page-title"><b>' + num + ':</b> ' +
                                    '<a href="' + escapeHtml(page.url) + '">' + escapeHtml(page.title) + '</a></span>' +
                                    '<span class="count-num">' + formatNumber(page.visitors) + '</span>';
                    list.appendChild(li);
                });
            });
        }

        function updateReferrers() {
            fetchJson('referrers').then(function (data) {
                var categoryLabels = {
                    direct: i18n.direct,
                    search: i18n.search,
                    social: i18n.social,
                    other: i18n.other
                };

                var catList = document.getElementById('m-referrer-categories-list');
                catList.innerHTML = '';
                var catTotal = ( data.categories.direct || 0 ) + ( data.categories.search || 0 ) +
                                ( data.categories.social || 0 ) + ( data.categories.other || 0 );
                ['direct', 'search', 'social', 'other'].forEach(function (key) {
                    var li = document.createElement('li');
                    var count = data.categories[key] || 0;
                    var pct = catTotal > 0 ? ( ( count / catTotal ) * 100 ).toFixed(1) : 0;
                    li.innerHTML = '<span class="page-title">' + escapeHtml(categoryLabels[key]) + ':</span>' +
                                    '<span class="value-wrap"><span class="count-num">' + formatNumber(count) + '</span>' +
                                    '<span class="count-pct">(' + pct + '%)</span></span>';
                    catList.appendChild(li);
                });

                var domainList = document.getElementById('m-referrer-domains-list');
                domainList.innerHTML = '';
                if (!data.domains || data.domains.length === 0) {
                    domainList.innerHTML = '<li class="empty">' + escapeHtml(i18n.noReferrers) + '</li>';
                    return;
                }
                data.domains.forEach(function (ref, index) {
                    var li = document.createElement('li');
                    var num = String(index + 1).padStart(2, '0');
                    li.innerHTML = '<span class="page-title"><b>' + num + ':</b> ' + escapeHtml(ref.domain) + ':</span>' +
                                    '<span class="count-num"> ' + formatNumber(ref.count) + '</span>';
                    domainList.appendChild(li);
                });
            });
        }

        function formatDuration(seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return m + ':' + String(s).padStart(2, '0');
        }

        function updateInsights() {
            fetchJson('insights').then(function (data) {
                var deviceLabels = { mobile: i18n.mobile, tablet: i18n.tablet, desktop: i18n.desktop };

                var deviceList = document.getElementById('m-devices-list');
                deviceList.innerHTML = '';
                var deviceTotal = ( data.devices.mobile || 0 ) + ( data.devices.tablet || 0 ) + ( data.devices.desktop || 0 );
                ['mobile', 'tablet', 'desktop'].forEach(function (key) {
                    var li = document.createElement('li');
                    var count = data.devices[key] || 0;
                    var pct = deviceTotal > 0 ? ( ( count / deviceTotal ) * 100 ).toFixed(1) : 0;
                    li.innerHTML = '<span class="page-title">' + escapeHtml(deviceLabels[key]) + ':</span>' +
                                    '<span class="value-wrap"><span class="count-num">' + formatNumber(count) + '</span>' +
                                    '<span class="count-pct">(' + pct + '%)</span></span>';
                    deviceList.appendChild(li);
                });

                document.getElementById('m-avg-time').textContent = ' ' + formatDuration(data.avg_time_seconds || 0);
            });
        }

        function refreshAll() {
            updateLiveCount();
            updateHistoryChart();
            updateTopPages();
            updateReferrers();
            updateInsights();
        }

        refreshAll();
        setInterval(updateLiveCount, 10000);
        setInterval(updateHistoryChart, 60000);
        setInterval(updateTopPages, 60000);
        setInterval(updateReferrers, 60000);
        setInterval(updateInsights, 60000);
    })();
    </script>
</body>
</html>
    <?php
}

/**
 * Tjekker om brugeren er admin, eller om den korrekte hemmelige token er angivet.
 * Bruges til at give adgang til den offentlige mobil-side uden login.
 */
/**
 * Tilføjer et live-besøgstal til WordPress' admin-bjælke (den sorte bar i toppen),
 * synligt på alle sider i wp-admin (og på frontend for indloggede admins).
 * Et klik fører direkte til VisitChart-dashboardet.
 */
function lstats_admin_bar_node( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) || ! lstats_is_admin_bar_enabled() ) {
        return;
    }

    $wp_admin_bar->add_node( array(
        'id'    => 'lstats-live',
        'title' => '<span class="ab-icon dashicons-chart-line" style="margin-top: 2px;"></span><span id="lstats-toolbar-count">…</span>',
        'href'  => admin_url( 'admin.php?page=lstats-dashboard' ),
        'meta'  => array(
            'title' => __( 'Live besøgende lige nu (klik for at åbne WP VisitChart)', 'wp-visitchart' ),
        ),
    ) );
}
add_action( 'admin_bar_menu', 'lstats_admin_bar_node', 100 );

/**
 * Indlæser et lille script, der opdaterer admin-bar-tallet hvert 10. sekund.
 * Kører både i wp-admin og på frontend (når admin-bjælken er synlig for en
 * indlogget administrator), men er holdt helt separat fra det store
 * dashboard-script, så det er minimalt og hurtigt at indlæse alle steder.
 */
function lstats_enqueue_toolbar_script() {
    if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! lstats_is_admin_bar_enabled() ) {
        return;
    }

    wp_register_script( 'lstats-toolbar', false, array(), '1.5.1', true );
    wp_enqueue_script( 'lstats-toolbar' );

    $rest_url = esc_url_raw( rest_url( 'lstats/v1/live-count' ) );
    $nonce    = wp_create_nonce( 'wp_rest' );

    $script = "
    (function() {
        function init() {
            var el = document.getElementById('lstats-toolbar-count');
            if (!el) { return; }
            function update() {
                fetch('" . esc_js( $rest_url ) . "', {
                    headers: { 'X-WP-Nonce': '" . esc_js( $nonce ) . "' },
                    credentials: 'same-origin'
                }).then(function(res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.json();
                }).then(function(data) {
                    if (el && typeof data.total !== 'undefined') {
                        el.textContent = data.total;
                    } else if (el) {
                        el.textContent = '–';
                    }
                }).catch(function() {
                    if (el) { el.textContent = '–'; }
                });
            }
            update();
            setInterval(update, 10000);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    ";
    wp_add_inline_script( 'lstats-toolbar', $script );
}
add_action( 'admin_enqueue_scripts', 'lstats_enqueue_toolbar_script' );
add_action( 'wp_enqueue_scripts', 'lstats_enqueue_toolbar_script' );

function lstats_can_view_stats( $request ) {
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    $token = $request->get_param( 'token' );
    $stored_token = get_option( 'lstats_public_token' );

    if ( $token && $stored_token && hash_equals( $stored_token, $token ) ) {
        return true;
    }

    return false;
}

/**
 * Registrer REST endpoints
 */
function lstats_register_routes() {
    register_rest_route( 'lstats/v1', '/heartbeat', array(
        'methods'             => 'POST',
        'callback'            => 'lstats_handle_heartbeat',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'lstats/v1', '/live-count', array(
        'methods'             => 'GET',
        'callback'            => 'lstats_get_live_count',
        'permission_callback' => 'lstats_can_view_stats',
    ) );

    register_rest_route( 'lstats/v1', '/today-history', array(
        'methods'             => 'GET',
        'callback'            => 'lstats_get_today_history',
        'permission_callback' => 'lstats_can_view_stats',
    ) );

    register_rest_route( 'lstats/v1', '/top-pages', array(
        'methods'             => 'GET',
        'callback'            => 'lstats_get_top_pages',
        'permission_callback' => 'lstats_can_view_stats',
    ) );

    register_rest_route( 'lstats/v1', '/referrers', array(
        'methods'             => 'GET',
        'callback'            => 'lstats_get_referrers',
        'permission_callback' => 'lstats_can_view_stats',
    ) );

    register_rest_route( 'lstats/v1', '/insights', array(
        'methods'             => 'GET',
        'callback'            => 'lstats_get_insights',
        'permission_callback' => 'lstats_can_view_stats',
    ) );

    // Offentligt endpoint – modtager et enkelt sidevisnings-ping fra
    // heartbeat.js. Validerer nonce internt for at undgå external abuse.
    register_rest_route( 'lstats/v1', '/pageview', array(
        'methods'             => 'POST',
        'callback'            => 'lstats_record_pageview',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'lstats_register_routes' );

/**
 * Forhindrer cache-plugins (WP Rocket m.fl.) og CDN'er (Cloudflare m.fl.)
 * i at cache vores REST-endpoints, så data altid er live
 */
function lstats_no_cache_headers( $served, $result, $request, $server ) {
    if ( false !== strpos( $request->get_route(), '/lstats/v1/' ) ) {
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }
    return $served;
}
add_filter( 'rest_pre_serve_request', 'lstats_no_cache_headers', 10, 4 );

/**
 * Fortæl WP Rocket at vores REST-ruter aldrig skal caches
 */
add_filter( 'rocket_cache_reject_uri', function( $uris ) {
    $uris[] = '(.*)wp-json/lstats/v1/(.*)';
    $uris[] = '(.*)lstats_mobile(.*)';
    return $uris;
} );

/**
 * Udeluk vores endpoints fra WP Rocket's preload-crawler
 */
add_filter( 'rocket_preload_exclude_urls', function( $urls ) {
    $urls[] = '(.*)wp-json/lstats/v1/(.*)';
    $urls[] = '(.*)lstats_mobile(.*)';
    return $urls;
} );

/**
 * Sæt en cookie der signalerer til WP Rocket, at mobilsiden aldrig må caches.
 * WP Rocket respekterer automatisk sider, der sætter en cookie med præfikset
 * "wordpress_" eller via rocket_cookies_white_list – vi bruger i stedet
 * do_not_cache-headeren, der er den officielle WP Rocket-måde at slå cache fra
 * på en specifik forespørgsel.
 */
add_action( 'template_redirect', function() {
    if ( isset( $_GET['lstats_mobile'] ) ) {
        if ( function_exists( 'rocket_clean_request' ) ) {
            header( 'X-Rocket-No-Cache: 1' );
        }
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }
}, 1 );

/**
 * Transients med lstats-data skal ikke gemmes i et persistent object cache
 * (Redis/Memcached), fordi de kan sidde fast langt længere end de 30 sekunder
 * vi sætter som TTL. Vi bruger wp_cache_delete til at rydde dem aktivt efter
 * hver skriveoperation i stedet for at stole på TTL-udløb.
 */
function lstats_bust_transient_cache() {
    $keys = array(
        'lstats_live_count',
        'lstats_live_pages',
        'lstats_top_pages',
        'lstats_referrers',
        'lstats_insights',
        'lstats_today_history',
    );
    foreach ( $keys as $key ) {
        wp_cache_delete( $key, 'options' );
        wp_cache_delete( '_transient_' . $key, 'options' );
        wp_cache_delete( '_transient_timeout_' . $key, 'options' );
    }
}
// Ryd object cache automatisk ved siden-indlæsning i admin,
// så dashboardet aldrig viser forældet data fra Redis/Memcached
add_action( 'admin_init', function() {
    if ( isset( $_GET['page'] ) && 'lstats-dashboard' === $_GET['page'] ) {
        lstats_bust_transient_cache();
    }
} );

// Ryd vores transients når WP Rocket invaliderer og genopbygger cache
// efter publicering eller opdatering af et indlæg/side.
// Dette forhindrer at preloaderen cacher tomme lstats-svar.
add_action( 'rocket_after_clean_post', function() {
    lstats_bust_transient_cache();
} );
add_action( 'rocket_preload_after_clean_post', function() {
    lstats_bust_transient_cache();
} );
add_action( 'after_rocket_clean_domain', function() {
    lstats_bust_transient_cache();
} );

/**
 * Modtag heartbeat fra frontend
 */
function lstats_handle_heartbeat( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_params();
    }

    $post_id    = isset( $params['postId'] ) ? absint( $params['postId'] ) : 0;
    $session_id = isset( $params['sessionId'] ) ? sanitize_text_field( $params['sessionId'] ) : '';
    $url        = isset( $params['url'] ) ? sanitize_text_field( $params['url'] ) : '';
    $referrer   = isset( $params['referrer'] ) ? sanitize_text_field( $params['referrer'] ) : '';
    $device     = isset( $params['device'] ) ? sanitize_text_field( $params['device'] ) : '';

    if ( empty( $session_id ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Manglende session_id' ), 400 );
    }

    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    $is_bot = lstats_is_bot_user_agent( $user_agent ) ? 1 : 0;

    if ( empty( $device ) ) {
        $device = lstats_guess_device_type( $user_agent );
    }

    $wpdb->insert(
        $table,
        array(
            'post_id'     => $post_id,
            'session_id'  => $session_id,
            'url'         => $url,
            'is_bot'      => $is_bot,
            'user_agent'  => $user_agent,
            'referrer'    => $referrer,
            'device_type' => $device,
            'created_at'  => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
    );

    return new WP_REST_Response( array( 'success' => true ), 200 );
}

/**
 * Hent antal live besøgende (aktive inden for de seneste 60 sekunder)
 * Caches i 8 sekunder via transient for at undgå tung load ved mange admin-viewers
 */
function lstats_get_live_count( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $cached = get_transient( 'lstats_live_count' );
    if ( false !== $cached ) {
        return new WP_REST_Response( $cached, 200 );
    }

    $threshold = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 120 );

    $total = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT session_id) FROM $table WHERE created_at >= %s AND is_bot = 0 AND source = 'heartbeat'",
        $threshold
    ) );

    $bot_total = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT session_id) FROM $table WHERE created_at >= %s AND is_bot = 1",
        $threshold
    ) );

    $per_page = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, COUNT(DISTINCT session_id) as live
         FROM $table
         WHERE created_at >= %s AND post_id > 0 AND is_bot = 0 AND source = 'heartbeat'
         GROUP BY post_id
         ORDER BY live DESC
         LIMIT 10",
        $threshold
    ) );

    $previous_threshold = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 240 );

    $previous_per_page = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, COUNT(DISTINCT session_id) as live
         FROM $table
         WHERE created_at >= %s AND created_at < %s AND post_id > 0 AND is_bot = 0 AND source = 'heartbeat'
         GROUP BY post_id",
        $previous_threshold,
        $threshold
    ) );

    $previous_counts = array();
    foreach ( $previous_per_page as $row ) {
        $previous_counts[ (int) $row->post_id ] = (int) $row->live;
    }

    // Hent alle post-titles og permalinks i ét samlet database-kald, i stedet for
    // at get_the_title/get_permalink foretager individuelle opslag i løkken nedenfor.
    $post_ids = wp_list_pluck( $per_page, 'post_id' );
    if ( ! empty( $post_ids ) ) {
        _prime_post_caches( array_map( 'intval', $post_ids ), false, false );
    }

    $pages = array();
    foreach ( $per_page as $row ) {
        $title = html_entity_decode( get_the_title( $row->post_id ), ENT_QUOTES, 'UTF-8' );
        $live_count = (int) $row->live;
        $prev_count = isset( $previous_counts[ (int) $row->post_id ] ) ? $previous_counts[ (int) $row->post_id ] : 0;

        // En side er "trending", hvis den har mindst 3 aktive besøgende lige nu,
        // og besøgstallet er steget mindst 50% i forhold til det foregående tidsvindue
        $is_trending = ( $live_count >= 3 && $live_count >= ( $prev_count * 1.5 ) && $live_count > $prev_count );

        $pages[] = array(
            'post_id'   => (int) $row->post_id,
            'title'     => $title ? $title : ( '#' . $row->post_id ),
            'live'      => $live_count,
            'url'       => get_permalink( $row->post_id ),
            'trending'  => $is_trending,
        );
    }

    $bot_pages = $wpdb->get_results( $wpdb->prepare(
        "SELECT session_id, user_agent
         FROM $table
         WHERE created_at >= %s AND is_bot = 1
         GROUP BY session_id",
        $threshold
    ) );

    $bot_counts = array();
    foreach ( $bot_pages as $row ) {
        $name = lstats_get_bot_name( $row->user_agent );
        if ( ! isset( $bot_counts[ $name ] ) ) {
            $bot_counts[ $name ] = 0;
        }
        $bot_counts[ $name ]++;
    }
    arsort( $bot_counts );

    $bots = array();
    $i = 0;
    foreach ( $bot_counts as $name => $count ) {
        if ( $i >= 10 ) {
            break;
        }
        $bots[] = array(
            'name'  => $name,
            'count' => $count,
        );
        $i++;
    }

    $result = array(
        'total'     => (int) $total,
        'bot_total' => (int) $bot_total,
        'pages'     => $pages,
        'bots'      => $bots,
    );

    set_transient( 'lstats_live_count', $result, 8 );
    wp_cache_delete( '_transient_lstats_live_count', 'options' );

    return new WP_REST_Response( $result, 200 );
}

/**
 * Hent dagens og gårsdagens historik i 5-minutters buckets,
 * så gårsdagens forløb kan tegnes som grå sammenligningskurve i baggrunden
 */
function lstats_get_today_history( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $cached = get_transient( 'lstats_today_history' );
    if ( false !== $cached ) {
        return new WP_REST_Response( $cached, 200 );
    }

    $today_date     = date( 'Y-m-d', current_time( 'timestamp' ) );
    $last_week_date = date( 'Y-m-d', current_time( 'timestamp' ) - ( 7 * 86400 ) );

    $today_start     = $today_date . ' 00:00:00';
    $today_end       = date( 'Y-m-d', current_time( 'timestamp' ) + 86400 ) . ' 00:00:00';
    $last_week_start = $last_week_date . ' 00:00:00';
    $last_week_end   = date( 'Y-m-d', current_time( 'timestamp' ) - ( 6 * 86400 ) ) . ' 00:00:00';

    // DATE(created_at) IN (...) tvinger MySQL til at beregne DATE() for hver række
    // og kan ikke bruge indekset på created_at (function-based full table scan).
    // Eksplicitte range-betingelser lader MySQL bruge idx_bot_source_time direkte.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT
            DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:%%i') as minute_bucket,
            session_id,
            post_id
         FROM $table
         WHERE is_bot = 0 AND source = 'heartbeat'
           AND (
               (created_at >= %s AND created_at < %s)
               OR
               (created_at >= %s AND created_at < %s)
           )
         ORDER BY created_at ASC",
        $today_start,
        $today_end,
        $last_week_start,
        $last_week_end
    ) );

    // Gruppér i 5-minutters buckets og tæl unikke sessions samt unikke sidevisninger,
    // separat for hver kalenderdag (i dag og samme ugedag sidste uge)
    $buckets = array();
    foreach ( $rows as $row ) {
        $day = substr( $row->minute_bucket, 0, 10 );
        $minute = intval( substr( $row->minute_bucket, -2 ) );
        $bucket_minute = floor( $minute / 5 ) * 5;
        $time_part = substr( $row->minute_bucket, 11, 2 ) . ':' . sprintf( '%02d', $bucket_minute );
        $bucket_key = $day . ' ' . $time_part;

        if ( ! isset( $buckets[ $bucket_key ] ) ) {
            $buckets[ $bucket_key ] = array(
                'sessions'  => array(),
                'pageviews' => array(),
            );
        }
        $buckets[ $bucket_key ]['sessions'][ $row->session_id ] = true;
        $buckets[ $bucket_key ]['pageviews'][ $row->session_id . '-' . $row->post_id ] = true;
    }

    // Byg en fuld liste over alle 288 5-minutters-intervaller for hele døgnet (00:00-23:55),
    // så grafen altid viser hele dagens tidsskala, selv hvor der ikke er data endnu
    $history = array();
    for ( $i = 0; $i < 288; $i++ ) {
        $hour = floor( $i / 12 );
        $minute = ( $i % 12 ) * 5;
        $time_label = sprintf( '%02d:%02d', $hour, $minute );

        $today_key     = $today_date . ' ' . $time_label;
        $last_week_key = $last_week_date . ' ' . $time_label;

        $history[] = array(
            'time'           => $time_label,
            'count'          => isset( $buckets[ $today_key ] ) ? count( $buckets[ $today_key ]['sessions'] ) : 0,
            'pageviews'      => isset( $buckets[ $today_key ] ) ? count( $buckets[ $today_key ]['pageviews'] ) : 0,
            'prevCount'      => isset( $buckets[ $last_week_key ] ) ? count( $buckets[ $last_week_key ]['sessions'] ) : 0,
            'prevPageviews'  => isset( $buckets[ $last_week_key ] ) ? count( $buckets[ $last_week_key ]['pageviews'] ) : 0,
        );
    }

    set_transient( 'lstats_today_history', $history, 30 );
    wp_cache_delete( '_transient_lstats_today_history', 'options' );

    return new WP_REST_Response( $history, 200 );
}

/**
 * Mest besøgte sider i dag (totalt, ikke kun live)
 */
function lstats_get_top_pages( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $start_of_day = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, COUNT(DISTINCT session_id) as visitors
         FROM $table
         WHERE created_at >= %s AND post_id > 0 AND is_bot = 0 AND source = 'heartbeat'
         GROUP BY post_id
         ORDER BY visitors DESC
         LIMIT 15",
        $start_of_day
    ) );

    $post_ids = wp_list_pluck( $rows, 'post_id' );
    if ( ! empty( $post_ids ) ) {
        _prime_post_caches( array_map( 'intval', $post_ids ), false, false );
    }

    $pages = array();
    foreach ( $rows as $row ) {
        $title = html_entity_decode( get_the_title( $row->post_id ), ENT_QUOTES, 'UTF-8' );
        $pages[] = array(
            'post_id'  => (int) $row->post_id,
            'title'    => $title ? $title : ( '#' . $row->post_id ),
            'visitors' => (int) $row->visitors,
            'url'      => get_permalink( $row->post_id ),
        );
    }

    return new WP_REST_Response( $pages, 200 );
}

/**
 * Hent dagens trafikkilder, opdelt i kategorier (direkte, søgning, sociale medier, andre)
 * samt en liste over de konkrete henvisende domæner inden for hver kategori
 */
function lstats_get_referrers( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $cached = get_transient( 'lstats_referrers' );
    if ( false !== $cached ) {
        return new WP_REST_Response( $cached, 200 );
    }

    $start_of_day = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

    // Henter kun den FØRSTE referrer/url per session (via MIN(id) subquery).
    // Langt færre rækker end at hente alle og deduplikere i PHP, og undgår
    // ONLY_FULL_GROUP_BY-problemer som opstår ved direkte GROUP BY på non-aggregated kolonner.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT h.session_id, h.referrer, h.url
         FROM $table h
         WHERE h.id IN (
             SELECT MIN(id)
             FROM $table
             WHERE created_at >= %s AND is_bot = 0 AND source = 'heartbeat'
             GROUP BY session_id
         )",
        $start_of_day
    ) );

    $categories = array(
        'direct' => 0,
        'search' => 0,
        'social' => 0,
        'other'  => 0,
    );
    $domains = array();

    foreach ( $rows as $row ) {
        $info = lstats_categorize_referrer( $row->referrer, $row->url );
        $categories[ $info['category'] ]++;

        if ( ! empty( $info['domain'] ) ) {
            $key = $info['category'] . '|' . $info['domain'];
            if ( ! isset( $domains[ $key ] ) ) {
                $domains[ $key ] = array(
                    'category' => $info['category'],
                    'domain'   => $info['domain'],
                    'count'    => 0,
                );
            }
            $domains[ $key ]['count']++;
        }
    }

    usort( $domains, function( $a, $b ) {
        return $b['count'] - $a['count'];
    } );

    $result = array(
        'categories' => $categories,
        'domains'    => array_slice( array_values( $domains ), 0, 15 ),
    );

    set_transient( 'lstats_referrers', $result, 30 );
    wp_cache_delete( '_transient_lstats_referrers', 'options' );

    return new WP_REST_Response( $result, 200 );
}

/**
 * Hent dagens indsigter: enhedsfordeling, geografi og gennemsnitlig tid på siden
 */
function lstats_get_insights( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;

    $cached = get_transient( 'lstats_insights' );
    if ( false !== $cached ) {
        return new WP_REST_Response( $cached, 200 );
    }

    $start_of_day = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

    // Enhedsfordeling: én GROUP BY-forespørgsel i SQL – ingen PHP-løkke nødvendig.
    // COUNT(DISTINCT session_id) sikrer at en session med mange heartbeats kun tælles én gang.
    $device_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT
            COALESCE(NULLIF(device_type, ''), 'desktop') AS device,
            COUNT(DISTINCT session_id) AS cnt
         FROM $table
         WHERE created_at >= %s AND is_bot = 0 AND source = 'heartbeat'
         GROUP BY device",
        $start_of_day
    ) );

    $device_counts = array( 'mobile' => 0, 'tablet' => 0, 'desktop' => 0 );
    foreach ( $device_rows as $row ) {
        $d = $row->device;
        if ( ! isset( $device_counts[ $d ] ) ) {
            $d = 'desktop';
        }
        $device_counts[ $d ] = (int) $row->cnt;
    }

    // Gennemsnitlig aktiv tid: henter kun de tre kolonner vi faktisk har brug for,
    // og sorterer i databasen så vi undgår sort() per session i PHP.
    $time_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT session_id, post_id, created_at
         FROM $table
         WHERE created_at >= %s AND is_bot = 0 AND source = 'heartbeat'
         ORDER BY session_id, post_id, created_at ASC",
        $start_of_day
    ) );

    $session_times = array();
    foreach ( $time_rows as $row ) {
        $time_key = $row->session_id . '-' . $row->post_id;
        $session_times[ $time_key ][] = strtotime( $row->created_at );
    }

    // Gennemsnitlig aktiv tid på siden: vi summerer kun mellemrummene mellem to
    // efterfølgende heartbeats, når mellemrummet er under 60 sekunder (heartbeat
    // sendes hvert 12. sekund, så 60 sekunder giver god margin for netværksudsving).
    // Er mellemrummet større, regnes besøgende for at have lagt fanen i baggrunden
    // eller forladt siden i den periode, og det tælles ikke som aktiv tid.
    // Besøg med kun ét heartbeat (varighed 0) tæller stadig med, men trækker
    // gennemsnittet en smule ned - det er en kendt og accepteret upræcision.
    $durations = array();
    foreach ( $session_times as $timestamps ) {
        // Timestamps er allerede sorteret fra SQL (ORDER BY session_id, post_id, created_at ASC)
        $active_seconds = 0;
        for ( $i = 1, $count = count( $timestamps ); $i < $count; $i++ ) {
            $gap = $timestamps[ $i ] - $timestamps[ $i - 1 ];
            if ( $gap <= 60 ) {
                $active_seconds += $gap;
            }
        }
        $durations[] = $active_seconds;
    }
    $avg_seconds = count( $durations ) > 0 ? round( array_sum( $durations ) / count( $durations ) ) : 0;

    $result = array(
        'devices'        => $device_counts,
        'avg_time_seconds' => $avg_seconds,
    );

    set_transient( 'lstats_insights', $result, 30 );
    wp_cache_delete( '_transient_lstats_insights', 'options' );

    return new WP_REST_Response( $result, 200 );
}

/**
 * Modtager og gemmer ét sidevisnings-ping fra heartbeat.js.
 * Nonce-valideres for at undgå kunstig inflation fra externe kald.
 * INSERT ... ON DUPLICATE KEY UPDATE håndterer atomisk:
 *   - Nyt indlæg: indsætter med views_today = 1
 *   - Eksisterende, samme dag: incrementerer views_today
 *   - Eksisterende, ny dag: lukker gårsdagens views_today ud til
 *     views_total og starter forfra med views_today = 1
 */
function lstats_record_pageview( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_REST_Response( array( 'success' => false ), 403 );
    }

    $post_id = absint( $request->get_param( 'postId' ) );
    if ( ! $post_id ) {
        return new WP_REST_Response( array( 'success' => false ), 400 );
    }

    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    if ( lstats_is_bot_user_agent( $user_agent ) ) {
        return new WP_REST_Response( array( 'success' => false ), 200 );
    }

    global $wpdb;
    $table = $wpdb->prefix . LSTATS_VIEWS_TABLE;
    $today = date( 'Y-m-d', current_time( 'timestamp' ) );
    $now   = current_time( 'mysql' );

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO $table (post_id, views_today, views_total, count_date, updated_at)
         VALUES (%d, 1, 0, %s, %s)
         ON DUPLICATE KEY UPDATE
             views_total = views_total + IF(count_date < %s, views_today, 0),
             views_today = IF(count_date < %s, 1, views_today + 1),
             count_date  = %s,
             updated_at  = %s",
        $post_id,
        $today,
        $now,
        $today,
        $today,
        $today,
        $now
    ) );

    return new WP_REST_Response( array( 'success' => true ), 200 );
}

function lstats_cleanup_old_data() {
    global $wpdb;
    $table = $wpdb->prefix . LSTATS_TABLE;
    $threshold = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 8 * 24 * 3600 ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", $threshold ) );
}

function lstats_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'lstats_cleanup_event' ) ) {
        wp_schedule_event( time(), 'hourly', 'lstats_cleanup_event' );
    }
}
add_action( 'wp', 'lstats_schedule_cleanup' );
add_action( 'lstats_cleanup_event', 'lstats_cleanup_old_data' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'lstats_cleanup_event' );
} );

function lstats_admin_menu() {
    add_menu_page(
        __( 'WP VisitChart', 'wp-visitchart' ),
        __( 'WP VisitChart', 'wp-visitchart' ),
        'manage_options',
        'lstats-dashboard',
        'lstats_render_dashboard',
        'dashicons-chart-line',
        3
    );

    add_submenu_page(
        'lstats-dashboard',
        __( 'WP VisitChart - Indstillinger', 'wp-visitchart' ),
        __( 'Indstillinger', 'wp-visitchart' ),
        'manage_options',
        'lstats-settings',
        'lstats_render_settings_page'
    );
}

/**
 * Sætter vores eget favicon i browserfanen, når man er på VisitChart-siden i wp-admin
 */
function lstats_admin_favicon() {
    $screen = get_current_screen();
    if ( ! $screen || 'toplevel_page_lstats-dashboard' !== $screen->id ) {
        return;
    }
    $icon_url = plugins_url( 'icons/icon-32.png', __FILE__ );
    echo '<link rel="icon" type="image/png" href="' . esc_url( $icon_url ) . '">';
}
add_action( 'admin_head', 'lstats_admin_favicon' );

// Giver .wrap ekstra padding-top på dashboard-siden så indholdet
// ikke gemmer sig bag den faste sticky-bjælke
add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( ! $screen || 'toplevel_page_lstats-dashboard' !== $screen->id ) {
        return;
    }
    echo '<style>
        .toplevel_page_lstats-dashboard #wpcontent { padding-top: 0; }
        .toplevel_page_lstats-dashboard .wrap { padding-top: 58px; padding-bottom: 0; }
        .toplevel_page_lstats-dashboard .wrap > h1 { margin-top: 6px !important; margin-bottom: 6px !important; padding: 0 !important; }
    </style>';
} );
add_action( 'admin_menu', 'lstats_admin_menu' );

/**
 * Håndterer klik på "Nulstil adgangskode"-knappen for mobil-siden
 */
function lstats_handle_reset_token() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Du har ikke rettigheder til dette.', 'wp-visitchart' ) );
    }

    check_admin_referer( 'lstats_reset_token_action', 'lstats_reset_token_nonce' );

    update_option( 'lstats_public_token', wp_generate_password( 32, false, false ) );

    wp_safe_redirect( admin_url( 'admin.php?page=lstats-settings&lstats_token_reset=1' ) );
    exit;
}
add_action( 'admin_post_lstats_reset_token', 'lstats_handle_reset_token' );

/**
 * Håndterer gem af indstillinger (admin-bar og mobilside til/fra)
 */

function lstats_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Du har ikke rettigheder til dette.', 'wp-visitchart' ) );
    }

    check_admin_referer( 'lstats_save_settings_action', 'lstats_save_settings_nonce' );

    update_option( 'lstats_admin_bar_enabled', isset( $_POST['lstats_admin_bar_enabled'] ) ? '1' : '0' );
    update_option( 'lstats_mobile_enabled', isset( $_POST['lstats_mobile_enabled'] ) ? '1' : '0' );
    update_option( 'lstats_post_views_enabled', isset( $_POST['lstats_post_views_enabled'] ) ? '1' : '0' );
    update_option( 'lstats_exclude_logged_in', isset( $_POST['lstats_exclude_logged_in'] ) ? '1' : '0' );

    wp_safe_redirect( admin_url( 'admin.php?page=lstats-settings&lstats_settings_saved=1' ) );
    exit;
}
add_action( 'admin_post_lstats_save_settings', 'lstats_handle_save_settings' );

function lstats_render_dashboard() {
    $version = '2.1.2';
    $year    = date( 'Y' );
    ?>
    <div class="wrap">
        <h1 style="margin-top: 6px !important; margin-bottom: 6px !important; padding: 0;"><?php esc_html_e( 'Live besøgende og dagens trafik', 'wp-visitchart' ); ?></h1>
        <div id="lstats-sticky-bar">
            <div class="lstats-sticky-item">
                <div class="lstats-sticky-label"><?php esc_html_e( 'Live besøgende', 'wp-visitchart' ); ?></div>
                <div class="lstats-sticky-number" id="lstats-sticky-total">0</div>
            </div>
        </div>
        <script>
        (function() {
            function positionStickyBar() {
                var bar = document.getElementById('lstats-sticky-bar');
                var menuWrap = document.getElementById('adminmenuwrap');
                if ( bar && menuWrap ) {
                    bar.style.left = menuWrap.offsetWidth + 'px';
                }
            }
            positionStickyBar();
            window.addEventListener('resize', positionStickyBar);
            setTimeout(positionStickyBar, 300);
        })();
        </script>
        <div id="lstats-app">
            <p><?php esc_html_e( 'Indlæser data...', 'wp-visitchart' ); ?></p>
        </div>
        <p style="margin-top: 24px; color: #8c8f94; font-size: 12px;">
            WP VisitChart <?php echo esc_html( $version ); ?> &mdash; Copyright &copy; 2026<?php if ( $year > 2026 ) { echo '&ndash;' . esc_html( $year ); } ?> Jens E. Hummelmose
        </p>
    </div>
    <?php
}

/**
 * Render-funktion for indstillingssiden
 */
function lstats_render_settings_page() {
    $admin_bar_enabled  = lstats_is_admin_bar_enabled();
    $mobile_enabled     = lstats_is_mobile_site_enabled();
    $post_views_enabled = lstats_is_post_views_enabled();
    $exclude_logged_in  = lstats_exclude_logged_in_users();
    $mobile_url         = add_query_arg( 'lstats_mobile', get_option( 'lstats_public_token' ), home_url( '/' ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'WP VisitChart - Indstillinger', 'wp-visitchart' ); ?></h1>

        <?php if ( isset( $_GET['lstats_settings_saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Indstillingerne er gemt.', 'wp-visitchart' ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['lstats_token_reset'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Adgangskoden er nulstillet. Det gamle mobil-link virker ikke længere.', 'wp-visitchart' ); ?></p>
            </div>
        <?php endif; ?>


        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="lstats_save_settings">
            <?php wp_nonce_field( 'lstats_save_settings_action', 'lstats_save_settings_nonce' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Admin-bjælke', 'wp-visitchart' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lstats_admin_bar_enabled" value="1" <?php checked( $admin_bar_enabled ); ?>>
                            <?php esc_html_e( 'Vis live besøgstal i admin-bjælken (den sorte bjælke i toppen)', 'wp-visitchart' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Slået fra fjernes tallet helt fra menuen, både i wp-admin og på frontend.', 'wp-visitchart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Mobilside', 'wp-visitchart' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lstats_mobile_enabled" value="1" <?php checked( $mobile_enabled ); ?>>
                            <?php esc_html_e( 'Aktiver den login-frie mobilside', 'wp-visitchart' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Slået fra viser mobilsiden en fejlmeddelelse, uanset om linket og adgangskoden ellers er korrekte.', 'wp-visitchart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sidevisninger i indlægsoversigten', 'wp-visitchart' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lstats_post_views_enabled" value="1" <?php checked( $post_views_enabled ); ?>>
                            <?php esc_html_e( 'Vis sidevisninger som kolonne i Posts- og Pages-oversigten', 'wp-visitchart' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Tilføjer en sorterbar "Sidevisninger"-kolonne i Posts- og Pages-oversigten. Tælles i realtid ved hvert besøg via et separat JavaScript-ping.', 'wp-visitchart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Udelad indloggede brugere', 'wp-visitchart' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lstats_exclude_logged_in" value="1" <?php checked( $exclude_logged_in ); ?>>
                            <?php esc_html_e( 'Tæl ikke besøg fra indloggede WordPress-brugere', 'wp-visitchart' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Slået til stopper heartbeat og sidevisnings-ping helt for indloggede brugere. Anbefales på redaktionelle sites, så redaktørers og forfatteres egne sidebesøg ikke tæller med i statistikken.', 'wp-visitchart' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Gem indstillinger', 'wp-visitchart' ) ); ?>
        </form>


        <hr>

        <h2><?php esc_html_e( 'Mobilside - adgang', 'wp-visitchart' ); ?></h2>
        <p>
            <?php esc_html_e( 'Mobil-side (uden login, til bogmærke på telefon):', 'wp-visitchart' ); ?><br>
            <?php if ( $mobile_enabled ) : ?>
                <a href="<?php echo esc_url( $mobile_url ); ?>" target="_blank"><?php echo esc_html( $mobile_url ); ?></a>
            <?php else : ?>
                <code><?php echo esc_html( $mobile_url ); ?></code>
                <em>(<?php esc_html_e( 'mobilsiden er slået fra ovenfor', 'wp-visitchart' ); ?>)</em>
            <?php endif; ?>
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="lstats_reset_token">
            <?php wp_nonce_field( 'lstats_reset_token_action', 'lstats_reset_token_nonce' ); ?>
            <?php submit_button( __( 'Nulstil adgangskode', 'wp-visitchart' ), 'secondary', 'submit', false, array(
                'onclick' => "return confirm('" . esc_js( __( 'Er du sikker? Det gamle mobil-link stopper med at virke.', 'wp-visitchart' ) ) . "');",
            ) ); ?>
        </form>
    </div>
    <?php
}

/**
 * Admin-kolonne: sidevisninger i posts- og pages-oversigten.
 * Kolonnen registreres altid (saa WordPress screen-options kan se den),
 * men viser kun data naer indstillingen er slaaet til.
 */
function lstats_add_views_column( $columns ) {
    $columns['lstats_views'] = __( 'Sidevisninger', 'wp-visitchart' );
    return $columns;
}
add_filter( 'manage_posts_columns', 'lstats_add_views_column' );
add_filter( 'manage_pages_columns', 'lstats_add_views_column' );
add_filter( 'manage_post_posts_columns', 'lstats_add_views_column' );
add_filter( 'manage_page_posts_columns', 'lstats_add_views_column' );

function lstats_render_views_column( $column, $post_id ) {
    if ( 'lstats_views' !== $column ) {
        return;
    }
    if ( ! lstats_is_post_views_enabled() ) {
        echo '<span style="color:#646970;">&ndash;</span>';
        return;
    }
    global $wpdb;
    $views_table = $wpdb->prefix . LSTATS_VIEWS_TABLE;
    $today = date( 'Y-m-d', current_time( 'timestamp' ) );
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT views_today, views_total, count_date FROM $views_table WHERE post_id = %d",
        (int) $post_id
    ) );
    if ( $row ) {
        $today_count = ( $row->count_date === $today ) ? (int) $row->views_today : 0;
        $total_count = (int) $row->views_total + (int) $row->views_today;
        if ( $total_count > 0 ) {
            echo '<span style="font-weight:600;">' . number_format_i18n( $total_count ) . '</span>';
            echo '<br><small style="color:#646970;">' . esc_html__( 'i dag', 'wp-visitchart' ) . ': ' . number_format_i18n( $today_count ) . '</small>';
        } else {
            echo '<span style="color:#646970;">&ndash;</span>';
        }
    } else {
        echo '<span style="color:#646970;">&ndash;</span>';
    }
}
add_action( 'manage_posts_custom_column', 'lstats_render_views_column', 10, 2 );
add_action( 'manage_pages_custom_column', 'lstats_render_views_column', 10, 2 );

function lstats_sortable_views_column( $columns ) {
    if ( lstats_is_post_views_enabled() ) {
        $columns['lstats_views'] = 'lstats_views';
    }
    return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', 'lstats_sortable_views_column' );
add_filter( 'manage_edit-page_sortable_columns', 'lstats_sortable_views_column' );

function lstats_views_column_join( $join, $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return $join;
    }
    if ( 'lstats_views' !== $query->get( 'orderby' ) ) {
        return $join;
    }
    global $wpdb;
    $views_table = $wpdb->prefix . LSTATS_VIEWS_TABLE;
    $join .= " LEFT JOIN {$views_table} ON ( {$wpdb->posts}.ID = {$views_table}.post_id ) ";
    return $join;
}
add_filter( 'posts_join', 'lstats_views_column_join', 10, 2 );

function lstats_views_column_orderby( $orderby, $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return $orderby;
    }
    if ( 'lstats_views' !== $query->get( 'orderby' ) ) {
        return $orderby;
    }
    global $wpdb;
    $views_table = $wpdb->prefix . LSTATS_VIEWS_TABLE;
    $order = 'ASC' === strtoupper( $query->get( 'order' ) ) ? 'ASC' : 'DESC';
    return "{$views_table}.views_total {$order}";
}
add_filter( 'posts_orderby', 'lstats_views_column_orderby', 10, 2 );

function lstats_views_column_css() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
        return;
    }
    if ( ! lstats_is_post_views_enabled() ) {
        return;
    }
    echo '<style>
        .column-lstats_views { width: 110px; }
        .manage-column.column-lstats_views { text-align: right; }
        td.column-lstats_views { text-align: right; }
    </style>';
}
add_action( 'admin_head', 'lstats_views_column_css' );

function lstats_enqueue_admin( $hook ) {
    if ( 'toplevel_page_lstats-dashboard' !== $hook ) {
        return;
    }

    wp_enqueue_script( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js', array(), '4.4.0', true );
    $plugin_version = '2.1.2';
    wp_enqueue_script( 'lstats-admin', plugins_url( 'admin-dashboard.js', __FILE__ ), array( 'chartjs' ), $plugin_version, true );
    wp_enqueue_style( 'lstats-admin-css', plugins_url( 'admin-dashboard.css', __FILE__ ), array(), $plugin_version );

    if ( ! lstats_is_fontawesome_loaded( 'admin' ) ) {
        wp_enqueue_style( 'lstats-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
    }

    wp_localize_script( 'lstats-admin', 'lstatsAdmin', array(
        'restUrl' => esc_url_raw( rest_url( 'lstats/v1/' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'locale'  => str_replace( '_', '-', get_locale() ),
        'i18n'    => array(
            'liveVisitorsNow'    => __( 'Live besøgende lige nu', 'wp-visitchart' ),
            'botsRegistered'     => __( 'bots registreret', 'wp-visitchart' ),
            'bot'                => __( 'bot', 'wp-visitchart' ),
            'visitorsToday'      => __( 'Besøgende i dag (5-minutters intervaller)', 'wp-visitchart' ),
            'mostActivePagesNow' => __( 'Mest aktive sider lige nu', 'wp-visitchart' ),
            'mostVisitedToday'   => __( 'Mest besøgte sider i dag', 'wp-visitchart' ),
            'noActivePages'      => __( 'Ingen aktive sider', 'wp-visitchart' ),
            'noDataToday'        => __( 'Ingen data endnu i dag', 'wp-visitchart' ),
            'uniqueVisitors'     => __( 'Unikke besøgende', 'wp-visitchart' ),
            'pageviews'          => __( 'Sidevisninger', 'wp-visitchart' ),
            'yesterday'          => __( 'samme ugedag sidste uge', 'wp-visitchart' ),
            'trafficSourcesToday' => __( 'Trafikkilder i dag', 'wp-visitchart' ),
            'direct'             => __( 'Direkte', 'wp-visitchart' ),
            'search'             => __( 'Søgemaskiner', 'wp-visitchart' ),
            'social'             => __( 'Sociale medier', 'wp-visitchart' ),
            'other'              => __( 'Andre hjemmesider', 'wp-visitchart' ),
            'topReferrers'       => __( 'Mest henvisende domæner', 'wp-visitchart' ),
            'noReferrers'        => __( 'Ingen henvisende domæner i dag', 'wp-visitchart' ),
            'devicesToday'       => __( 'Enheder i dag', 'wp-visitchart' ),
            'mobile'             => __( 'Mobil', 'wp-visitchart' ),
            'tablet'             => __( 'Tablet', 'wp-visitchart' ),
            'desktop'            => __( 'Desktop', 'wp-visitchart' ),
            'avgTimeOnPage'      => __( 'Gns. tid på sitet', 'wp-visitchart' ),
            'trending'           => __( 'Trending', 'wp-visitchart' ),
        ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'lstats_enqueue_admin', 100 );

