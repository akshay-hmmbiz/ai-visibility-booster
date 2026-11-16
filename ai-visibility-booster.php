<?php
/**
 * Plugin Name: AI Visibility Booster
 * Plugin URI:  https://github.com/akshay-hmmbiz/ai-visibility-booster
 * Description: Improves machine readability of WordPress websites for AI systems like ChatGPT, Claude, Gemini, Perplexity, and Bing AI. Generates AI sitemaps, structured data, content summaries, and more.
 * Version:     1.1.1
 * Author:      Akshay Bhimani
 * Author URI:  https://github.com/akshay-hmmbiz
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-visibility-booster
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package AI_Visibility_Booster
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AIVB_VERSION', '1.1.1' );
define( 'AIVB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIVB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIVB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files.
require_once AIVB_PLUGIN_DIR . 'includes/summary-generator.php';
require_once AIVB_PLUGIN_DIR . 'includes/ai-sitemap.php';
require_once AIVB_PLUGIN_DIR . 'includes/ai-feed.php';
require_once AIVB_PLUGIN_DIR . 'includes/schema-generator.php';
require_once AIVB_PLUGIN_DIR . 'includes/visibility-score.php';
require_once AIVB_PLUGIN_DIR . 'includes/markdown-export.php';
require_once AIVB_PLUGIN_DIR . 'includes/knowledge-graph.php';
require_once AIVB_PLUGIN_DIR . 'includes/bot-analytics.php';

if ( is_admin() ) {
	require_once AIVB_PLUGIN_DIR . 'admin/dashboard.php';
	require_once AIVB_PLUGIN_DIR . 'admin/analytics-page.php';
}

// ─── Activation & Deactivation ───────────────────────────────────────────────

/**
 * Plugin activation callback.
 * Sets default options and flushes rewrite rules.
 */
function aivb_activate() {
	// Register defaults only if they don't exist yet.
	$defaults = array(
		'aivb_enable_summary'         => '1',
		'aivb_enable_sitemap'         => '1',
		'aivb_enable_feed'            => '1',
		'aivb_enable_schema'          => '1',
		'aivb_enable_markdown'        => '1',
		'aivb_enable_knowledge_graph' => '1',
		'aivb_enable_analytics'       => '1',
		'aivb_analytics_retention'    => '90',
	);

	foreach ( $defaults as $option => $value ) {
		add_option( $option, $value );
	}

	// Create bot analytics database table.
	aivb_create_analytics_table();

	aivb_register_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'aivb_activate' );

/**
 * Plugin deactivation callback.
 * Cleans up transients and flushes rewrite rules.
 */
function aivb_deactivate() {
	delete_transient( 'aivb_sitemap_cache' );
	delete_transient( 'aivb_feed_cache' );
	delete_transient( 'aivb_knowledge_cache' );
	wp_clear_scheduled_hook( 'aivb_weekly_cleanup' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aivb_deactivate' );

// ─── Rewrite Rules ────────────────────────────────────────────────────────────

/**
 * Register custom rewrite rules for all AI endpoints.
 */
function aivb_register_rewrite_rules() {
	add_rewrite_rule(
		'^ai-sitemap\.xml$',
		'index.php?aivb_endpoint=ai-sitemap',
		'top'
	);
	add_rewrite_rule(
		'^ai-feed\.json$',
		'index.php?aivb_endpoint=ai-feed',
		'top'
	);
	add_rewrite_rule(
		'^ai-knowledge\.json$',
		'index.php?aivb_endpoint=ai-knowledge',
		'top'
	);
	add_rewrite_rule(
		'^ai-markdown/([a-zA-Z0-9_-]+)/?$',
		'index.php?aivb_endpoint=ai-markdown&aivb_slug=$matches[1]',
		'top'
	);
}
add_action( 'init', 'aivb_register_rewrite_rules' );

/**
 * Register custom query variables.
 *
 * @param array $vars Existing query vars.
 * @return array Modified query vars.
 */
function aivb_query_vars( $vars ) {
	$vars[] = 'aivb_endpoint';
	$vars[] = 'aivb_slug';
	return $vars;
}
add_filter( 'query_vars', 'aivb_query_vars' );

// ─── Endpoint Router ──────────────────────────────────────────────────────────

/**
 * Route custom endpoints to the appropriate output functions.
 */
function aivb_template_redirect() {
	$endpoint = get_query_var( 'aivb_endpoint' );

	if ( empty( $endpoint ) ) {
		return;
	}

	switch ( $endpoint ) {
		case 'ai-sitemap':
			if ( '1' === get_option( 'aivb_enable_sitemap', '1' ) ) {
				aivb_output_sitemap();
			}
			break;

		case 'ai-feed':
			if ( '1' === get_option( 'aivb_enable_feed', '1' ) ) {
				aivb_output_feed();
			}
			break;

		case 'ai-knowledge':
			if ( '1' === get_option( 'aivb_enable_knowledge_graph', '1' ) ) {
				aivb_output_knowledge_graph();
			}
			break;

		case 'ai-markdown':
			if ( '1' === get_option( 'aivb_enable_markdown', '1' ) ) {
				$slug = sanitize_title( get_query_var( 'aivb_slug' ) );
				if ( ! empty( $slug ) ) {
					aivb_output_markdown( $slug );
				}
			}
			break;
	}

	// If endpoint matched but feature disabled, serve a 404.
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	$template = get_404_template();
	if ( $template ) {
		include $template;
	}
	exit;
}
add_action( 'template_redirect', 'aivb_template_redirect' );

// ─── REST API Routes ──────────────────────────────────────────────────────────

/**
 * Register REST API routes used by the Gutenberg sidebar.
 */
function aivb_register_rest_routes() {
	register_rest_route(
		'aivb/v1',
		'/score/(?P<post_id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'aivb_rest_get_score',
			'permission_callback' => static function ( $request ) {
				// The user must be able to edit this specific post, not just any post.
				return current_user_can( 'edit_post', absint( $request['post_id'] ) );
			},
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'minimum'           => 1,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'aivb_register_rest_routes' );

/**
 * REST API callback: return visibility score for a post.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function aivb_rest_get_score( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'post_id' ) );
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return new WP_Error(
			'aivb_not_found',
			__( 'Post not found.', 'ai-visibility-booster' ),
			array( 'status' => 404 )
		);
	}

	return rest_ensure_response( aivb_calculate_visibility_score( $post_id ) );
}

// ─── Assets ───────────────────────────────────────────────────────────────────

/**
 * Enqueue admin assets (dashboard and post edit screens).
 *
 * @param string $hook Current admin page hook.
 */
function aivb_enqueue_admin_assets( $hook ) {
	$allowed_hooks = array( 'post.php', 'post-new.php' );

	// The Bot Analytics submenu hook does not contain the top-level slug, so match it separately.
	if ( false === strpos( $hook, 'ai-visibility-booster' ) && false === strpos( $hook, 'aivb-analytics' ) && ! in_array( $hook, $allowed_hooks, true ) ) {
		return;
	}

	wp_enqueue_style(
		'aivb-admin',
		AIVB_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		AIVB_VERSION
	);

	wp_enqueue_script(
		'aivb-admin',
		AIVB_PLUGIN_URL . 'assets/js/admin.js',
		array( 'jquery' ),
		AIVB_VERSION,
		true
	);

	wp_localize_script(
		'aivb-admin',
		'aivbAdmin',
		array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'bulkGenerateNonce' => wp_create_nonce( 'aivb_bulk_generate' ),
			'strings'           => array(
				'generate'     => __( 'Bulk Generate Missing Summaries', 'ai-visibility-booster' ),
				'generating'   => __( 'Generating summaries…', 'ai-visibility-booster' ),
				'done'         => __( 'Done!', 'ai-visibility-booster' ),
				'error'        => __( 'An error occurred. Please try again.', 'ai-visibility-booster' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'aivb_enqueue_admin_assets' );

/**
 * Enqueue Gutenberg block editor sidebar assets.
 */
function aivb_enqueue_editor_assets() {
	wp_enqueue_style(
		'aivb-editor',
		AIVB_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		AIVB_VERSION
	);

	wp_enqueue_script(
		'aivb-editor-sidebar',
		AIVB_PLUGIN_URL . 'assets/js/editor-sidebar.js',
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
		AIVB_VERSION,
		true
	);

	wp_localize_script(
		'aivb-editor-sidebar',
		'aivbData',
		array(
			'apiBase'  => esc_url_raw( rest_url( 'aivb/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'version'  => AIVB_VERSION,
			'siteUrl'  => esc_url( home_url() ),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'aivb_enqueue_editor_assets' );

// ─── Plugin Action Links ──────────────────────────────────────────────────────

/**
 * Add Dashboard link to plugins list.
 *
 * @param array $links Existing action links.
 * @return array Modified action links.
 */
function aivb_plugin_action_links( $links ) {
	$dashboard_link = '<a href="' . esc_url( admin_url( 'admin.php?page=ai-visibility-booster' ) ) . '">'
		. esc_html__( 'Dashboard', 'ai-visibility-booster' )
		. '</a>';

	array_unshift( $links, $dashboard_link );
	return $links;
}
add_filter( 'plugin_action_links_' . AIVB_PLUGIN_BASENAME, 'aivb_plugin_action_links' );

// ─── i18n ─────────────────────────────────────────────────────────────────────

/**
 * Load plugin text domain.
 */
function aivb_load_textdomain() {
	load_plugin_textdomain(
		'ai-visibility-booster',
		false,
		dirname( AIVB_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'aivb_load_textdomain' );
