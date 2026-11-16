<?php
/**
 * AI Sitemap Generator
 *
 * Generates an AI-friendly XML sitemap at /ai-sitemap.xml
 * This is a supplementary sitemap specifically designed for AI crawlers.
 * It does NOT conflict with Yoast, RankMath, or other SEO sitemaps.
 *
 * Privacy note: No visitor or user data is included.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * XML-safe escape helper with WordPress 5.5 compatibility.
 *
 * @param string $text Text to escape.
 * @return string Escaped text.
 */
function aivb_esc_xml( $text ) {
	if ( function_exists( 'esc_xml' ) ) {
		return esc_xml( $text );
	}
	// Fallback for WordPress < 5.5.
	return htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Output the AI-friendly XML sitemap.
 *
 * Queries up to 500 published posts/pages, includes post metadata,
 * and caches the result for 12 hours using a transient.
 * Cache is automatically invalidated when any post is saved.
 */
function aivb_output_sitemap() {
	// Serve from cache if available.
	$cached = get_transient( 'aivb_sitemap_cache' );

	if ( false !== $cached ) {
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	// Query published content.
	$args = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'has_password'   => false,
		'posts_per_page' => 500,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	$posts = get_posts( $args );

	// Build XML output.
	$output  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$output .= '<!-- AI Visibility Booster v' . AIVB_VERSION . ' | Plugin by Akshay Bhimani -->' . "\n";
	$output .= '<!-- This sitemap is for AI/LLM crawlers. It is supplementary to your main SEO sitemap. -->' . "\n";
	$output .= '<ai-sitemap xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n";
	$output .= '  <meta>' . "\n";
	$output .= '    <generated>' . aivb_esc_xml( gmdate( 'c' ) ) . '</generated>' . "\n";
	$output .= '    <site-name>' . aivb_esc_xml( get_bloginfo( 'name' ) ) . '</site-name>' . "\n";
	$output .= '    <site-url>' . aivb_esc_xml( home_url( '/' ) ) . '</site-url>' . "\n";
	$output .= '    <total-posts>' . count( $posts ) . '</total-posts>' . "\n";
	$output .= '  </meta>' . "\n";
	$output .= '  <posts>' . "\n";

	foreach ( $posts as $post ) {
		$post_id  = absint( $post->ID );
		$summary  = aivb_get_summary( $post_id );
		$cats     = get_the_category( $post_id );
		$cat_name = ! empty( $cats ) ? $cats[0]->name : '';
		$tags     = get_the_tags( $post_id );
		$tag_list = '';

		if ( is_array( $tags ) && ! empty( $tags ) ) {
			$tag_names = wp_list_pluck( $tags, 'name' );
			$tag_list  = implode( ', ', array_map( 'sanitize_text_field', $tag_names ) );
		}

		$output .= '    <post>' . "\n";
		$output .= '      <title>' . aivb_esc_xml( $post->post_title ) . '</title>' . "\n";
		$output .= '      <url>' . aivb_esc_xml( get_permalink( $post_id ) ) . '</url>' . "\n";
		$output .= '      <summary>' . aivb_esc_xml( $summary ) . '</summary>' . "\n";
		$output .= '      <published>' . aivb_esc_xml( get_the_date( 'c', $post_id ) ) . '</published>' . "\n";
		$output .= '      <lastmod>' . aivb_esc_xml( get_the_modified_date( 'c', $post_id ) ) . '</lastmod>' . "\n";
		$output .= '      <type>' . aivb_esc_xml( $post->post_type ) . '</type>' . "\n";
		$output .= '      <category>' . aivb_esc_xml( $cat_name ) . '</category>' . "\n";
		$output .= '      <tags>' . aivb_esc_xml( $tag_list ) . '</tags>' . "\n";
		$output .= '    </post>' . "\n";
	}

	$output .= '  </posts>' . "\n";
	$output .= '</ai-sitemap>' . "\n";

	// Cache for 12 hours.
	set_transient( 'aivb_sitemap_cache', $output, 12 * HOUR_IN_SECONDS );

	nocache_headers();
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex' );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $output;
	exit;
}
