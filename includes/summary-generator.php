<?php
/**
 * AI Content Summary Generator
 *
 * Automatically extracts a concise AI-friendly summary from each post/page,
 * stores it in post meta, and outputs it as a <meta> tag in the page head.
 *
 * Privacy note: No visitor data is stored. Only post content is processed.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Summary Generation ───────────────────────────────────────────────────────

/**
 * Generate an AI-friendly summary from a post's content.
 *
 * Extracts the first paragraph, strips HTML, and trims to ~160 characters.
 * Returns an empty string if no content is available.
 *
 * @param int $post_id Post ID.
 * @return string Sanitized summary text.
 */
function aivb_generate_summary( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || empty( $post->post_content ) ) {
		return '';
	}

	// Run through content filters so shortcodes etc. are processed.
	$content = apply_filters( 'the_content', $post->post_content );

	// Try to extract the first <p> element.
	if ( preg_match( '/<p[^>]*>(.*?)<\/p>/si', $content, $matches ) && ! empty( $matches[1] ) ) {
		$summary = $matches[1];
	} else {
		$summary = $content;
	}

	// Strip all HTML tags and normalize whitespace.
	$summary = wp_strip_all_tags( $summary );
	$summary = preg_replace( '/\s+/', ' ', $summary );
	$summary = trim( $summary );

	// Trim to a reasonable summary length at a word boundary.
	if ( mb_strlen( $summary ) > 200 ) {
		$summary = wp_trim_words( $summary, 35, '...' );
	}

	return sanitize_text_field( $summary );
}

// ─── Save on Post Save ────────────────────────────────────────────────────────

/**
 * Generate and save AI summary when a post is saved.
 *
 * Skips revisions, autosaves, and non-published posts.
 * Only runs for 'post' and 'page' post types.
 *
 * @param int $post_id Post ID.
 */
function aivb_save_summary_on_post_save( $post_id ) {
	// Skip revisions and autosaves.
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return;
	}

	// Only process standard post types.
	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}

	// Flush endpoint caches so they regenerate with fresh data. This runs for
	// every status change (including unpublish, trash and private) so a post
	// that is no longer public does not stay in a cached endpoint.
	delete_transient( 'aivb_sitemap_cache' );
	delete_transient( 'aivb_feed_cache' );
	delete_transient( 'aivb_knowledge_cache' );

	// Only generate summaries for published posts and pages.
	if ( ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}

	$summary = aivb_generate_summary( $post_id );

	if ( ! empty( $summary ) ) {
		update_post_meta( $post_id, '_aivb_summary', $summary );
	}
}
add_action( 'save_post', 'aivb_save_summary_on_post_save' );

// ─── Get Summary ─────────────────────────────────────────────────────────────

/**
 * Retrieve the stored AI summary for a post, generating it on-demand if absent.
 *
 * @param int|null $post_id Post ID. Defaults to current post.
 * @return string Summary text.
 */
function aivb_get_summary( $post_id = null ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return '';
	}

	// Never expose a summary of password-protected content.
	if ( '' !== (string) get_post_field( 'post_password', $post_id ) ) {
		return '';
	}

	$summary = get_post_meta( $post_id, '_aivb_summary', true );

	// Generate and cache if not stored yet.
	if ( empty( $summary ) ) {
		$summary = aivb_generate_summary( $post_id );
		if ( ! empty( $summary ) ) {
			update_post_meta( $post_id, '_aivb_summary', $summary );
		}
	}

	return (string) $summary;
}

// ─── Head Output ─────────────────────────────────────────────────────────────

/**
 * Output AI summary as a <meta> tag in the page <head>.
 *
 * Skips output when Yoast SEO, RankMath, or AIOSEO is active, as those
 * plugins already output a meta description. The AI summary tag uses a
 * distinct name ("ai-summary") to remain compatible with all SEO plugins.
 */
function aivb_output_summary_meta() {
	if ( ! is_singular() ) {
		return;
	}

	// Respect the "AI Content Summaries" toggle on the settings page.
	if ( '1' !== get_option( 'aivb_enable_summary', '1' ) ) {
		return;
	}

	// Always output the ai-summary meta tag regardless of SEO plugin presence.
	$post_id = get_the_ID();
	$summary = aivb_get_summary( $post_id );

	if ( empty( $summary ) ) {
		return;
	}

	// Output the dedicated AI summary tag (does not conflict with SEO plugins).
	echo '<meta name="ai-summary" content="' . esc_attr( $summary ) . '">' . "\n";

	// Only output standard meta description if no SEO plugin is handling it.
	$seo_plugin_active = defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| class_exists( 'AIOSEOP_Class' );

	if ( ! $seo_plugin_active ) {
		echo '<meta name="description" content="' . esc_attr( $summary ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'aivb_output_summary_meta', 5 );

// ─── Bulk Generation ──────────────────────────────────────────────────────────

/**
 * Generate summaries for all published posts/pages that don't have one yet.
 *
 * Called from the admin dashboard "Bulk Generate" button.
 *
 * @return int Number of summaries generated.
 */
function aivb_bulk_generate_summaries() {
	$args = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => '_aivb_summary',
				'compare' => 'NOT EXISTS',
			),
		),
	);

	$post_ids  = get_posts( $args );
	$generated = 0;

	foreach ( $post_ids as $post_id ) {
		$summary = aivb_generate_summary( $post_id );
		if ( ! empty( $summary ) ) {
			update_post_meta( $post_id, '_aivb_summary', $summary );
			++$generated;
		}
	}

	// Flush caches after bulk generation.
	delete_transient( 'aivb_sitemap_cache' );
	delete_transient( 'aivb_feed_cache' );
	delete_transient( 'aivb_knowledge_cache' );

	return $generated;
}

// ─── Stats Helper ─────────────────────────────────────────────────────────────

/**
 * Count posts that currently have an AI summary stored.
 *
 * @return int Number of posts with a summary.
 */
function aivb_count_posts_with_summary() {
	global $wpdb;

	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT post_id)
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = %s
			   AND meta_value != ''",
			'_aivb_summary'
		)
	);

	return absint( $count );
}
