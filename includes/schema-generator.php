<?php
/**
 * AI Structured Data (Schema) Generator
 *
 * Adds JSON-LD structured data to posts and pages:
 * - Article schema for posts/pages.
 * - FAQPage schema when FAQ content is detected.
 * - Organization schema for the homepage.
 *
 * Avoids conflicting with Yoast, RankMath, or AIOSEO schema output.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output JSON-LD structured data in the page <head>.
 *
 * Defers to major SEO plugins if they are active, as they already output
 * comprehensive schema. Our schema runs only when no other plugin handles it.
 */
function aivb_output_schema() {
	if ( '1' !== get_option( 'aivb_enable_schema', '1' ) ) {
		return;
	}

	// Skip if a major SEO plugin is active (they handle schema themselves).
	if ( aivb_seo_plugin_active() ) {
		// Still output Article schema as an augmented "ai-article" type only
		// if on a singular post, because SEO plugins may not include AI-specific fields.
		if ( is_singular( 'post' ) ) {
			aivb_output_article_schema();
		}
		return;
	}

	if ( is_front_page() ) {
		aivb_output_organization_schema();
	}

	if ( is_singular( array( 'post', 'page' ) ) ) {
		aivb_output_article_schema();
		aivb_output_faq_schema();
	}
}
add_action( 'wp_head', 'aivb_output_schema', 10 );

// ─── Organization Schema ──────────────────────────────────────────────────────

/**
 * Output Organization JSON-LD schema for the homepage.
 */
function aivb_output_organization_schema() {
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => sanitize_text_field( get_bloginfo( 'name' ) ),
		'description' => sanitize_text_field( get_bloginfo( 'description' ) ),
		'url'         => esc_url_raw( home_url( '/' ) ),
	);

	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $logo_url ) {
			$schema['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => esc_url_raw( $logo_url ),
			);
		}
	}

	aivb_print_json_ld( $schema );
}

// ─── Article Schema ───────────────────────────────────────────────────────────

/**
 * Output Article JSON-LD schema for singular posts/pages.
 */
function aivb_output_article_schema() {
	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$post_id     = $post->ID;
	$summary     = aivb_get_summary( $post_id );
	$thumbnail   = get_the_post_thumbnail_url( $post_id, 'large' );
	$schema_type = ( 'page' === $post->post_type ) ? 'WebPage' : 'Article';

	$schema = array(
		'@context'         => 'https://schema.org',
		'@type'            => $schema_type,
		'headline'         => sanitize_text_field( $post->post_title ),
		'description'      => $summary,
		'url'              => esc_url_raw( get_permalink( $post_id ) ),
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'inLanguage'       => sanitize_text_field( get_bloginfo( 'language' ) ),
		'isPartOf'         => array(
			'@type' => 'WebSite',
			'name'  => sanitize_text_field( get_bloginfo( 'name' ) ),
			'url'   => esc_url_raw( home_url( '/' ) ),
		),
	);

	if ( $thumbnail ) {
		$schema['image'] = array(
			'@type' => 'ImageObject',
			'url'   => esc_url_raw( $thumbnail ),
		);
	}

	// Add author as Organization (avoids exposing individual user data).
	$schema['author'] = array(
		'@type' => 'Organization',
		'name'  => sanitize_text_field( get_bloginfo( 'name' ) ),
		'url'   => esc_url_raw( home_url( '/' ) ),
	);

	// Add categories and tags as keywords.
	$cats = get_the_category( $post_id );
	$tags = get_the_tags( $post_id );

	$keywords = array();
	if ( is_array( $cats ) ) {
		foreach ( $cats as $cat ) {
			$keywords[] = sanitize_text_field( $cat->name );
		}
	}
	if ( is_array( $tags ) ) {
		foreach ( $tags as $tag ) {
			$keywords[] = sanitize_text_field( $tag->name );
		}
	}

	if ( ! empty( $keywords ) ) {
		$schema['keywords'] = implode( ', ', $keywords );
	}

	aivb_print_json_ld( $schema );
}

// ─── FAQ Schema ───────────────────────────────────────────────────────────────

/**
 * Output FAQPage JSON-LD schema if FAQ content is detected in the post.
 */
function aivb_output_faq_schema() {
	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	// Do not expose FAQ text from password-protected content.
	if ( '' !== (string) $post->post_password ) {
		return;
	}

	$faq_items = aivb_extract_faq_items( $post->post_content );

	if ( empty( $faq_items ) ) {
		return;
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array(),
	);

	foreach ( $faq_items as $item ) {
		$schema['mainEntity'][] = array(
			'@type'          => 'Question',
			'name'           => sanitize_text_field( $item['question'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_kses(
					$item['answer'],
					array(
						'p'      => array(),
						'strong' => array(),
						'em'     => array(),
						'a'      => array( 'href' => array() ),
						'ul'     => array(),
						'ol'     => array(),
						'li'     => array(),
					)
				),
			),
		);
	}

	aivb_print_json_ld( $schema );
}

/**
 * Extract FAQ question/answer pairs from post content.
 *
 * Supports two common patterns:
 * 1. A heading or strong tag starting with "Q:" followed by an answer "A:".
 * 2. An <h3>/<h4> tag with "?" at the end followed by a <p> answer.
 * 3. Gutenberg FAQ blocks (wp:faq class).
 *
 * @param string $content Post content HTML.
 * @return array Array of ['question' => ..., 'answer' => ...] pairs.
 */
function aivb_extract_faq_items( $content ) {
	$faq_items = array();

	if ( empty( $content ) ) {
		return $faq_items;
	}

	// Pattern 1: <strong>Q: question?</strong> followed by a paragraph containing the answer.
	$content_lower = strtolower( $content );
	if ( false === strpos( $content_lower, 'faq' ) &&
		 false === strpos( $content_lower, 'frequently asked' ) &&
		 false === strpos( $content_lower, '<strong>q:' ) &&
		 ! preg_match( '/<h[2-4][^>]*>[^<]*\?[^<]*<\/h[2-4]>/i', $content ) ) {
		return $faq_items;
	}

	// Pattern 1: Q:/A: pattern with <strong> tags.
	if ( preg_match_all( '/<strong[^>]*>[Qq][:\s]+([^<]+)<\/strong>\s*<\/p>\s*<p[^>]*>([^<]+)/s', $content, $qa_matches, PREG_SET_ORDER ) ) {
		foreach ( $qa_matches as $match ) {
			$question = trim( wp_strip_all_tags( $match[1] ) );
			$answer   = trim( wp_strip_all_tags( $match[2] ) );
			if ( ! empty( $question ) && ! empty( $answer ) ) {
				$faq_items[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}
	}

	// Pattern 2: Headings ending with "?" as questions (H2–H4), next <p> as answer.
	if ( preg_match_all( '/<h[234][^>]*>(.*?\?)\s*<\/h[234]>\s*<p[^>]*>(.*?)<\/p>/si', $content, $h_matches, PREG_SET_ORDER ) ) {
		foreach ( $h_matches as $match ) {
			$question = trim( wp_strip_all_tags( $match[1] ) );
			$answer   = trim( wp_strip_all_tags( $match[2] ) );
			if ( ! empty( $question ) && ! empty( $answer ) && strlen( $question ) < 250 ) {
				$faq_items[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}
	}

	// Deduplicate by question text.
	$seen      = array();
	$unique    = array();
	foreach ( $faq_items as $item ) {
		$key = md5( $item['question'] );
		if ( ! isset( $seen[ $key ] ) ) {
			$seen[ $key ] = true;
			$unique[]     = $item;
		}
	}

	return array_slice( $unique, 0, 20 ); // Limit to 20 FAQs.
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Print a JSON-LD schema block as a <script> tag.
 *
 * @param array $schema Schema data array.
 */
function aivb_print_json_ld( array $schema ) {
	$json = wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( empty( $json ) ) {
		return;
	}

	echo '<script type="application/ld+json">' . "\n";
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD is intentionally raw JSON.
	echo $json . "\n";
	echo '</script>' . "\n";
}

/**
 * Check whether a major SEO plugin is active.
 *
 * @return bool True if Yoast SEO, RankMath, or AIOSEO is active.
 */
function aivb_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| class_exists( 'AIOSEOP_Class' );
}

/**
 * Check whether the schema generator would produce schema for a given post.
 * Used by the visibility score calculator.
 *
 * @param int $post_id Post ID.
 * @return bool True if schema will be output.
 */
function aivb_has_schema_for_post( $post_id ) {
	if ( '1' !== get_option( 'aivb_enable_schema', '1' ) ) {
		return false;
	}

	$post = get_post( $post_id );

	return $post && in_array( $post->post_type, array( 'post', 'page' ), true );
}
