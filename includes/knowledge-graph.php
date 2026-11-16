<?php
/**
 * AI Knowledge Graph Builder
 *
 * Generates a structured knowledge graph JSON at /ai-knowledge.json that
 * describes the site and its content entities for AI systems. Also outputs
 * a JSON-LD WebSite/SiteNavigationElement schema in the page <head>.
 *
 * Privacy note: No visitor or user data is included. Author names are
 * omitted to minimise PII exposure.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Knowledge Graph JSON Endpoint ────────────────────────────────────────────

/**
 * Output the AI knowledge graph JSON at /ai-knowledge.json.
 *
 * Returns a structured representation of the site's content entities,
 * categories, and top-level navigation. Cached for 12 hours.
 */
function aivb_output_knowledge_graph() {
	// Serve from cache if available.
	$cached = get_transient( 'aivb_knowledge_cache' );

	if ( false !== $cached ) {
		aivb_send_knowledge_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	// ── Site-level metadata ────────────────────────────────────────────────────
	$site_data = array(
		'name'        => sanitize_text_field( get_bloginfo( 'name' ) ),
		'description' => sanitize_text_field( get_bloginfo( 'description' ) ),
		'url'         => esc_url_raw( home_url( '/' ) ),
		'language'    => sanitize_text_field( get_bloginfo( 'language' ) ),
	);

	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $logo_url ) {
			$site_data['logo'] = esc_url_raw( $logo_url );
		}
	}

	// ── Categories as topic entities ──────────────────────────────────────────
	$categories    = get_categories( array( 'hide_empty' => true ) );
	$topic_entities = array();

	foreach ( $categories as $cat ) {
		$topic_entities[] = array(
			'id'          => absint( $cat->term_id ),
			'name'        => sanitize_text_field( $cat->name ),
			'url'         => esc_url_raw( get_category_link( $cat->term_id ) ),
			'count'       => absint( $cat->count ),
			'description' => sanitize_text_field( $cat->description ),
		);
	}

	// ── Tags as concept entities ───────────────────────────────────────────────
	$tags            = get_tags( array( 'hide_empty' => true, 'number' => 100 ) );
	$concept_entities = array();

	if ( is_array( $tags ) ) {
		foreach ( $tags as $tag ) {
			$concept_entities[] = array(
				'id'    => absint( $tag->term_id ),
				'name'  => sanitize_text_field( $tag->name ),
				'url'   => esc_url_raw( get_tag_link( $tag->term_id ) ),
				'count' => absint( $tag->count ),
			);
		}
	}

	// ── Recent/important posts as content entities ─────────────────────────────
	$posts = get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 200,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	$content_entities = array();

	foreach ( $posts as $post ) {
		$post_id = absint( $post->ID );
		$summary = aivb_get_summary( $post_id );
		$cats    = get_the_category( $post_id );

		$cat_names = array();
		if ( is_array( $cats ) ) {
			foreach ( $cats as $cat ) {
				$cat_names[] = sanitize_text_field( $cat->name );
			}
		}

		$post_tags = get_the_tags( $post_id );
		$tag_names = array();
		if ( is_array( $post_tags ) ) {
			foreach ( $post_tags as $tag ) {
				$tag_names[] = sanitize_text_field( $tag->name );
			}
		}

		$entity = array(
			'id'         => $post_id,
			'type'       => sanitize_key( $post->post_type ),
			'title'      => sanitize_text_field( $post->post_title ),
			'url'        => esc_url_raw( get_permalink( $post_id ) ),
			'summary'    => $summary,
			'published'  => get_the_date( 'c', $post_id ),
			'modified'   => get_the_modified_date( 'c', $post_id ),
			'categories' => $cat_names,
			'tags'       => $tag_names,
		);

		$thumbnail = get_the_post_thumbnail_url( $post_id, 'medium' );
		if ( $thumbnail ) {
			$entity['image'] = esc_url_raw( $thumbnail );
		}

		$content_entities[] = $entity;
	}

	// ── Navigation: top-level pages ───────────────────────────────────────────
	$nav_pages    = get_pages(
		array(
			'sort_column'  => 'menu_order',
			'sort_order'   => 'ASC',
			'hierarchical' => false,
			'parent'       => 0,
		)
	);
	$navigation = array();

	if ( is_array( $nav_pages ) ) {
		foreach ( $nav_pages as $page ) {
			$navigation[] = array(
				'title' => sanitize_text_field( $page->post_title ),
				'url'   => esc_url_raw( get_permalink( $page->ID ) ),
			);
		}
	}

	// ── Assemble graph ────────────────────────────────────────────────────────
	$graph = array(
		'version'          => '1.0',
		'generated'        => gmdate( 'c' ),
		'plugin'           => 'AI Visibility Booster by Akshay Bhimani',
		'site'             => $site_data,
		'navigation'       => $navigation,
		'topics'           => $topic_entities,
		'concepts'         => $concept_entities,
		'total_content'    => count( $content_entities ),
		'content_entities' => $content_entities,
	);

	$output = wp_json_encode( $graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $output ) {
		$output = '{"error":"JSON encoding failed"}';
	}

	// Cache for 12 hours.
	set_transient( 'aivb_knowledge_cache', $output, 12 * HOUR_IN_SECONDS );

	aivb_send_knowledge_headers();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $output;
	exit;
}

/**
 * Send HTTP headers for the knowledge graph JSON endpoint.
 */
function aivb_send_knowledge_headers() {
	nocache_headers();
	header( 'Content-Type: application/json; charset=UTF-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Vary: Accept-Encoding' );
}

// ─── WebSite JSON-LD Schema ────────────────────────────────────────────────────

/**
 * Output a WebSite JSON-LD schema block in the page <head> on the homepage.
 *
 * This provides a sitelinks searchbox hint and overall site identity to
 * search engines and AI systems. Only outputs when no major SEO plugin is
 * active (to prevent duplication).
 */
function aivb_output_website_schema() {
	if ( '1' !== get_option( 'aivb_enable_schema', '1' ) ) {
		return;
	}

	if ( aivb_seo_plugin_active() ) {
		return;
	}

	if ( ! is_front_page() ) {
		return;
	}

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'WebSite',
		'name'            => sanitize_text_field( get_bloginfo( 'name' ) ),
		'description'     => sanitize_text_field( get_bloginfo( 'description' ) ),
		'url'             => esc_url_raw( home_url( '/' ) ),
		'inLanguage'      => sanitize_text_field( get_bloginfo( 'language' ) ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => esc_url_raw( home_url( '/?s={search_term_string}' ) ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	if ( function_exists( 'aivb_print_json_ld' ) ) {
		aivb_print_json_ld( $schema );
	}
}
add_action( 'wp_head', 'aivb_output_website_schema', 11 );
