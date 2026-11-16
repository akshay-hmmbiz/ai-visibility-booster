<?php
/**
 * AI Discovery JSON Feed
 *
 * Creates a structured JSON feed at /ai-feed.json for AI systems to
 * discover and index site content. Follows open-access principles with
 * appropriate CORS headers so AI crawlers can access it.
 *
 * Privacy note: No visitor or user data is included. Post author names
 * are omitted by default to minimize PII exposure.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output the AI discovery JSON feed.
 *
 * Returns up to 500 published posts/pages as structured JSON.
 * Cached for 12 hours; cache is busted when any post is saved.
 */
function aivb_output_feed() {
	// Serve from cache if available.
	$cached = get_transient( 'aivb_feed_cache' );

	if ( false !== $cached ) {
		aivb_send_feed_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	$args = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'has_password'   => false,
		'posts_per_page' => 500,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	$posts      = get_posts( $args );
	$feed_items = array();

	foreach ( $posts as $post ) {
		$post_id  = absint( $post->ID );
		$cats     = get_the_category( $post_id );
		$tags     = get_the_tags( $post_id );
		$summary  = aivb_get_summary( $post_id );

		$cat_names = array();
		if ( is_array( $cats ) ) {
			foreach ( $cats as $cat ) {
				$cat_names[] = sanitize_text_field( $cat->name );
			}
		}

		$tag_names = array();
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_names[] = sanitize_text_field( $tag->name );
			}
		}

		$feed_items[] = array(
			'id'         => $post_id,
			'title'      => sanitize_text_field( $post->post_title ),
			'url'        => esc_url_raw( get_permalink( $post_id ) ),
			'summary'    => $summary,
			'date'       => get_the_date( 'c', $post_id ),
			'modified'   => get_the_modified_date( 'c', $post_id ),
			'type'       => sanitize_key( $post->post_type ),
			'categories' => $cat_names,
			'tags'       => $tag_names,
		);
	}

	$feed_data = array(
		'version'     => '1.0',
		'generated'   => gmdate( 'c' ),
		'plugin'      => 'AI Visibility Booster by Akshay Bhimani',
		'site'        => array(
			'name'        => sanitize_text_field( get_bloginfo( 'name' ) ),
			'description' => sanitize_text_field( get_bloginfo( 'description' ) ),
			'url'         => esc_url_raw( home_url( '/' ) ),
			'language'    => sanitize_text_field( get_bloginfo( 'language' ) ),
		),
		'total_posts' => count( $feed_items ),
		'posts'       => $feed_items,
	);

	$output = wp_json_encode( $feed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $output ) {
		$output = '{"error":"JSON encoding failed"}';
	}

	// Cache for 12 hours.
	set_transient( 'aivb_feed_cache', $output, 12 * HOUR_IN_SECONDS );

	aivb_send_feed_headers();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $output;
	exit;
}

/**
 * Send HTTP headers appropriate for an AI JSON feed.
 * Allows open access from AI crawlers (CORS *).
 */
function aivb_send_feed_headers() {
	nocache_headers();
	header( 'Content-Type: application/json; charset=UTF-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Vary: Accept-Encoding' );
}
