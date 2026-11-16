<?php
/**
 * AI Visibility Booster – Bot Analytics
 *
 * Detects and logs visits from known AI crawlers, search bots, and
 * citation bots. All data is stored in a local WordPress database table.
 *
 * PRIVACY: No personal data is collected. IP addresses are salted and
 * hashed with SHA-256 before storage and are never stored in plain text.
 * No data is sent to any external server. Everything stays on your site.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Known AI & Search Bots ───────────────────────────────────────────────────

/**
 * Returns the list of recognised AI/search bots.
 *
 * Keys are User-Agent string fragments (case-insensitive match).
 * 'type' is 'crawl' (indexing) or 'citation' (content attribution).
 *
 * @return array<string, array{name: string, company: string, type: string}>
 */
function aivb_get_known_bots() {
	return array(
		'ClaudeBot'          => array( 'name' => 'ClaudeBot',       'company' => 'Anthropic',    'type' => 'crawl'    ),
		'anthropic-ai'       => array( 'name' => 'Anthropic AI',    'company' => 'Anthropic',    'type' => 'crawl'    ),
		'OAI-SearchBot'      => array( 'name' => 'OAI-SearchBot',   'company' => 'OpenAI',       'type' => 'crawl'    ),
		'GPTBot'             => array( 'name' => 'GPTBot',          'company' => 'OpenAI',       'type' => 'crawl'    ),
		'ChatGPT-User'       => array( 'name' => 'ChatGPT-User',    'company' => 'OpenAI',       'type' => 'citation' ),
		'AI_Citation'        => array( 'name' => 'AI_Citation',     'company' => 'Unknown',      'type' => 'citation' ),
		'PerplexityBot'      => array( 'name' => 'PerplexityBot',   'company' => 'Perplexity',   'type' => 'crawl'    ),
		'Bytespider'         => array( 'name' => 'Bytespider',      'company' => 'ByteDance',    'type' => 'crawl'    ),
		'cohere-ai'          => array( 'name' => 'Cohere AI',       'company' => 'Cohere',       'type' => 'crawl'    ),
		'Applebot'           => array( 'name' => 'Applebot',        'company' => 'Apple',        'type' => 'crawl'    ),
		'YouBot'             => array( 'name' => 'YouBot',          'company' => 'You.com',      'type' => 'crawl'    ),
		'Diffbot'            => array( 'name' => 'Diffbot',         'company' => 'Diffbot',      'type' => 'crawl'    ),
		'meta-externalagent' => array( 'name' => 'Meta AI',         'company' => 'Meta',         'type' => 'crawl'    ),
		'Google-Extended'    => array( 'name' => 'Google-Extended', 'company' => 'Google',       'type' => 'crawl'    ),
		'Googlebot'          => array( 'name' => 'Googlebot',       'company' => 'Google',       'type' => 'crawl'    ),
		'bingbot'            => array( 'name' => 'Bingbot',         'company' => 'Microsoft',    'type' => 'crawl'    ),
		'CCBot'              => array( 'name' => 'CCBot',           'company' => 'Common Crawl', 'type' => 'crawl'    ),
	);
}

// ─── Database Table ───────────────────────────────────────────────────────────

/**
 * Create the bot visits table on plugin activation.
 * Uses dbDelta() so it is safe to call multiple times.
 */
function aivb_create_analytics_table() {
	global $wpdb;

	$table           = $wpdb->prefix . 'aivb_bot_visits';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		bot_name    varchar(100)        NOT NULL DEFAULT '',
		bot_company varchar(100)        NOT NULL DEFAULT '',
		bot_type    varchar(20)         NOT NULL DEFAULT 'crawl',
		user_agent  text                NOT NULL,
		page_url    varchar(500)        NOT NULL DEFAULT '',
		ip_hash     varchar(64)         NOT NULL DEFAULT '',
		session_id  varchar(64)         NOT NULL DEFAULT '',
		visit_time  datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (id),
		KEY idx_bot_name   (bot_name),
		KEY idx_visit_time (visit_time),
		KEY idx_session    (session_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'aivb_analytics_db_version', '1.1' );
}

// ─── Bot Detection & Logging ──────────────────────────────────────────────────

/**
 * Detect AI bot visits and write a row to the analytics table.
 * Hooked to 'wp' (after main query, before template selection).
 */
function aivb_detect_and_log_bot() {
	if ( is_admin() ) {
		return;
	}

	if ( '1' !== get_option( 'aivb_enable_analytics', '1' ) ) {
		return;
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( empty( $ua ) ) {
		return;
	}

	$matched = null;
	foreach ( aivb_get_known_bots() as $fragment => $bot ) {
		if ( false !== stripos( $ua, $fragment ) ) {
			$matched = $bot;
			break;
		}
	}
	if ( null === $matched ) {
		return;
	}

	// Hash the IP with a site-specific salt — non-reversible, GDPR-safe.
	$raw_ip   = '';
	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$raw_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		// Only use the first IP in a potential comma-separated list.
		$raw_ip = trim( explode( ',', $raw_ip )[0] );
	} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$raw_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	$ip_hash = hash( 'sha256', $raw_ip . wp_salt( 'auth' ) );

	// Sessions: same bot + IP hash within a 30-minute window = same session.
	$session_seed = $ip_hash . $matched['name'] . floor( time() / 1800 );
	$session_id   = hash( 'sha256', $session_seed );

	$page_url = '';
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$page_url = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		$wpdb->prefix . 'aivb_bot_visits',
		array(
			'bot_name'    => $matched['name'],
			'bot_company' => $matched['company'],
			'bot_type'    => $matched['type'],
			'user_agent'  => substr( $ua, 0, 500 ),
			'page_url'    => substr( $page_url, 0, 500 ),
			'ip_hash'     => $ip_hash,
			'session_id'  => $session_id,
			'visit_time'  => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}
add_action( 'wp', 'aivb_detect_and_log_bot', 5 );

// ─── Data Retrieval API ───────────────────────────────────────────────────────

/**
 * Get overall stats for the analytics dashboard.
 *
 * @param int $days Days to look back. 0 = all time.
 * @return array{total_visits:int, today_visits:int, unique_pages:int, unique_bots:int, total_citations:int, total_sessions:int}
 */
function aivb_get_analytics_stats( $days = 30 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'aivb_bot_visits';

	$where_clause  = '';
	$where_values  = array();
	if ( $days > 0 ) {
		$since        = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$where_clause = 'WHERE visit_time >= %s';
		$where_values = array( $since );
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $where_values ) {
		$total_visits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_clause}", $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique_pages = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT page_url) FROM {$table} {$where_clause}", $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique_bots  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT bot_name) FROM {$table} {$where_clause}", $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$citations    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_clause} AND bot_type = 'citation'", $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sessions     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_id) FROM {$table} {$where_clause}", $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	} else {
		$total_visits = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$unique_pages = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT page_url) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$unique_bots  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT bot_name) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$citations    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE bot_type = 'citation'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sessions     = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$today_start  = gmdate( 'Y-m-d' ) . ' 00:00:00';
	$today_visits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visit_time >= %s", $today_start ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return array(
		'total_visits'    => $total_visits,
		'today_visits'    => $today_visits,
		'unique_pages'    => $unique_pages,
		'unique_bots'     => $unique_bots,
		'total_citations' => $citations,
		'total_sessions'  => $sessions,
	);
}

/**
 * Get top bots ranked by visit count.
 *
 * @param int $days  Days to look back.
 * @param int $limit Max rows.
 * @return array
 */
function aivb_get_top_bots( $days = 30, $limit = 10 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'aivb_bot_visits';
	$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				bot_name,
				bot_company,
				bot_type,
				COUNT(*)                    AS total_visits,
				COUNT(DISTINCT page_url)    AS unique_pages,
				COUNT(DISTINCT session_id)  AS sessions
			FROM {$table}
			WHERE visit_time >= %s
			GROUP BY bot_name, bot_company, bot_type
			ORDER BY total_visits DESC
			LIMIT %d",
			$since,
			$limit
		)
	);
}

/**
 * Get daily visit totals for the last N days.
 *
 * @param int $days Number of days.
 * @return array  Rows: visit_date (Y-m-d), visits (int).
 */
function aivb_get_daily_totals( $days = 14 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'aivb_bot_visits';
	$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(visit_time) AS visit_date, COUNT(*) AS visits
			FROM {$table}
			WHERE visit_time >= %s
			GROUP BY DATE(visit_time)
			ORDER BY visit_date ASC",
			$since
		)
	);
}

/**
 * Get bot-by-bot breakdown of visit counts.
 *
 * @param int $days Days to look back. 0 = all time.
 * @return array  Rows: bot_name, visits.
 */
function aivb_get_bot_breakdown( $days = 30 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'aivb_bot_visits';

	if ( $days > 0 ) {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_name, COUNT(*) AS visits FROM {$table} WHERE visit_time >= %s GROUP BY bot_name ORDER BY visits DESC",
				$since
			)
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_results(
		"SELECT bot_name, COUNT(*) AS visits FROM {$wpdb->prefix}aivb_bot_visits GROUP BY bot_name ORDER BY visits DESC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);
}

/**
 * Get the most recent bot visits for the activity log.
 *
 * @param int $limit Max rows.
 * @return array
 */
function aivb_get_recent_visits( $limit = 25 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'aivb_bot_visits';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT bot_name, bot_type, page_url, visit_time FROM {$table} ORDER BY visit_time DESC LIMIT %d",
			$limit
		)
	);
}

// ─── Scheduled Cleanup ────────────────────────────────────────────────────────

/**
 * Delete records older than the configured retention period (default 90 days).
 * Runs on the weekly cron event 'aivb_weekly_cleanup'.
 */
function aivb_purge_old_analytics() {
	global $wpdb;
	$retention = max( 7, (int) get_option( 'aivb_analytics_retention', 90 ) );
	$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}aivb_bot_visits WHERE visit_time < %s",
			$cutoff
		)
	);
}
add_action( 'aivb_weekly_cleanup', 'aivb_purge_old_analytics' );

/**
 * Ensure the weekly cleanup cron is scheduled on every front-end load.
 */
function aivb_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'aivb_weekly_cleanup' ) ) {
		wp_schedule_event( time(), 'weekly', 'aivb_weekly_cleanup' );
	}
}
add_action( 'wp', 'aivb_schedule_cleanup' );
