<?php
/**
 * AI Visibility Score Calculator
 *
 * Scores each post/page out of 100 based on AI-readability criteria:
 * heading structure, AI summary presence, internal links, content length,
 * and structured data. Displays the score in the editor sidebar and
 * as a classic editor meta box.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Score Calculation ────────────────────────────────────────────────────────

/**
 * Calculate the AI Visibility Score for a post.
 *
 * Scoring breakdown (max 100 points):
 * - H1 heading present         : 10 pts
 * - H2 headings present        : 10 pts
 * - AI summary stored          : 20 pts
 * - Internal links count       : up to 20 pts
 * - Content word count         : up to 20 pts
 * - Structured data present    : 20 pts
 *
 * @param int $post_id Post ID.
 * @return array {
 *   @type int    $score     Total score (0–100).
 *   @type int    $max       Always 100.
 *   @type string $label     Human-readable label (Excellent/Good/etc.).
 *   @type array  $breakdown Keyed criteria with score, max, status.
 * }
 */
function aivb_calculate_visibility_score( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return array(
			'score'     => 0,
			'max'       => 100,
			'label'     => __( 'N/A', 'ai-visibility-booster' ),
			'breakdown' => array(),
			'post_id'   => $post_id,
		);
	}

	$content   = (string) $post->post_content;
	$score     = 0;
	$breakdown = array();

	// ── Criterion 1: H1 heading (10 pts) ──────────────────────────────────────
	$has_h1    = (bool) preg_match( '/<h1[\s>]/i', $content );
	$h1_pts    = $has_h1 ? 10 : 0;
	$score    += $h1_pts;

	$breakdown['h1'] = array(
		'label'  => __( 'H1 Heading', 'ai-visibility-booster' ),
		'score'  => $h1_pts,
		'max'    => 10,
		'status' => $has_h1 ? 'pass' : 'fail',
		'tip'    => $has_h1 ? '' : __( 'Add an H1 heading to your content.', 'ai-visibility-booster' ),
	);

	// ── Criterion 2: H2 headings (10 pts) ─────────────────────────────────────
	$has_h2    = (bool) preg_match( '/<h2[\s>]/i', $content );
	$h2_pts    = $has_h2 ? 10 : 0;
	$score    += $h2_pts;

	$breakdown['h2'] = array(
		'label'  => __( 'H2 Subheadings', 'ai-visibility-booster' ),
		'score'  => $h2_pts,
		'max'    => 10,
		'status' => $has_h2 ? 'pass' : 'warn',
		'tip'    => $has_h2 ? '' : __( 'Add H2 subheadings to structure your content.', 'ai-visibility-booster' ),
	);

	// ── Criterion 3: AI summary stored (20 pts) ───────────────────────────────
	$summary     = get_post_meta( $post_id, '_aivb_summary', true );
	$has_summary = ! empty( $summary );
	$sum_pts     = $has_summary ? 20 : 0;
	$score      += $sum_pts;

	$breakdown['summary'] = array(
		'label'  => __( 'AI Summary', 'ai-visibility-booster' ),
		'score'  => $sum_pts,
		'max'    => 20,
		'status' => $has_summary ? 'pass' : 'fail',
		'tip'    => $has_summary ? '' : __( 'Save/publish the post to generate an AI summary.', 'ai-visibility-booster' ),
	);

	// ── Criterion 4: Internal links (up to 20 pts) ────────────────────────────
	$site_url      = preg_quote( home_url(), '/' );
	$link_pattern  = '/<a[^>]+href=["\'][^"\']*' . $site_url . '[^"\']*["\'][^>]*>/i';
	preg_match_all( $link_pattern, $content, $link_matches );
	$link_count    = count( $link_matches[0] );

	if ( $link_count >= 10 ) {
		$link_pts = 20;
	} elseif ( $link_count >= 5 ) {
		$link_pts = 15;
	} elseif ( $link_count >= 1 ) {
		$link_pts = 10;
	} else {
		$link_pts = 0;
	}
	$score += $link_pts;

	$breakdown['internal_links'] = array(
		/* translators: %d: number of internal links found */
		'label'  => sprintf( __( 'Internal Links (%d found)', 'ai-visibility-booster' ), $link_count ),
		'score'  => $link_pts,
		'max'    => 20,
		'status' => $link_count > 0 ? 'pass' : 'warn',
		'tip'    => $link_count > 0 ? '' : __( 'Add internal links to related posts.', 'ai-visibility-booster' ),
	);

	// ── Criterion 5: Content word count (up to 20 pts) ────────────────────────
	$word_count = str_word_count( wp_strip_all_tags( $content ) );

	if ( $word_count >= 1000 ) {
		$len_pts = 20;
	} elseif ( $word_count >= 600 ) {
		$len_pts = 15;
	} elseif ( $word_count >= 300 ) {
		$len_pts = 10;
	} elseif ( $word_count >= 100 ) {
		$len_pts = 5;
	} else {
		$len_pts = 0;
	}
	$score += $len_pts;

	$breakdown['content_length'] = array(
		/* translators: %d: number of words in post content */
		'label'  => sprintf( __( 'Content Length (%d words)', 'ai-visibility-booster' ), $word_count ),
		'score'  => $len_pts,
		'max'    => 20,
		'status' => $word_count >= 300 ? 'pass' : 'warn',
		'tip'    => $word_count >= 300 ? '' : __( 'Aim for at least 300 words of content.', 'ai-visibility-booster' ),
	);

	// ── Criterion 6: Structured data present (20 pts) ─────────────────────────
	$schema_present = aivb_seo_plugin_active()
		|| ( function_exists( 'aivb_has_schema_for_post' ) && aivb_has_schema_for_post( $post_id ) );

	$schema_pts = $schema_present ? 20 : 0;
	$score     += $schema_pts;

	$breakdown['schema'] = array(
		'label'  => __( 'Structured Data (Schema)', 'ai-visibility-booster' ),
		'score'  => $schema_pts,
		'max'    => 20,
		'status' => $schema_present ? 'pass' : 'fail',
		'tip'    => $schema_present ? '' : __( 'Enable schema in AI Visibility Booster settings.', 'ai-visibility-booster' ),
	);

	return array(
		'score'     => $score,
		'max'       => 100,
		'label'     => aivb_score_label( $score ),
		'breakdown' => $breakdown,
		'post_id'   => $post_id,
	);
}

/**
 * Convert a numeric score to a human-readable label.
 *
 * @param int $score Score between 0 and 100.
 * @return string Label string.
 */
function aivb_score_label( $score ) {
	if ( $score >= 80 ) {
		return __( 'Excellent', 'ai-visibility-booster' );
	}
	if ( $score >= 60 ) {
		return __( 'Good', 'ai-visibility-booster' );
	}
	if ( $score >= 40 ) {
		return __( 'Moderate', 'ai-visibility-booster' );
	}
	return __( 'Needs Improvement', 'ai-visibility-booster' );
}

/**
 * Calculate the average AI Visibility Score across all published posts/pages.
 *
 * Samples up to 50 recent posts for performance.
 *
 * @return float Average score (0–100).
 */
function aivb_average_visibility_score() {
	$args = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'fields'         => 'ids',
	);

	$post_ids = get_posts( $args );

	if ( empty( $post_ids ) ) {
		return 0.0;
	}

	$total = 0;
	foreach ( $post_ids as $post_id ) {
		$data   = aivb_calculate_visibility_score( $post_id );
		$total += absint( $data['score'] );
	}

	return round( $total / count( $post_ids ), 1 );
}

// ─── Classic Editor Meta Box ──────────────────────────────────────────────────

/**
 * Register the AI Visibility Score meta box for the classic editor.
 */
function aivb_register_score_meta_box() {
	$post_types = array( 'post', 'page' );

	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'aivb_visibility_score',
			__( 'AI Visibility Score', 'ai-visibility-booster' ),
			'aivb_render_score_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'aivb_register_score_meta_box' );

/**
 * Render the AI Visibility Score meta box content.
 *
 * @param WP_Post $post Current post object.
 */
function aivb_render_score_meta_box( WP_Post $post ) {
	$data       = aivb_calculate_visibility_score( $post->ID );
	$score      = absint( $data['score'] );
	$label      = esc_html( $data['label'] );
	$breakdown  = $data['breakdown'];
	$percent    = $score;
	$color_class = 'aivb-score-poor';

	if ( $score >= 80 ) {
		$color_class = 'aivb-score-excellent';
	} elseif ( $score >= 60 ) {
		$color_class = 'aivb-score-good';
	} elseif ( $score >= 40 ) {
		$color_class = 'aivb-score-moderate';
	}

	?>
	<div class="aivb-metabox-wrap">
		<div class="aivb-score-circle <?php echo esc_attr( $color_class ); ?>">
			<span class="aivb-score-number"><?php echo esc_html( $score ); ?></span>
			<span class="aivb-score-max">/100</span>
		</div>
		<p class="aivb-score-label"><strong><?php echo esc_html( $label ); ?></strong></p>
		<div class="aivb-score-bar-wrap">
			<div class="aivb-score-bar" style="width:<?php echo esc_attr( $percent ); ?>%"></div>
		</div>
		<hr>
		<ul class="aivb-breakdown-list">
			<?php foreach ( $breakdown as $criterion ) : ?>
				<?php
				$icon = 'fail' === $criterion['status'] ? '✗' : ( 'warn' === $criterion['status'] ? '⚠' : '✓' );
				$cls  = 'fail' === $criterion['status'] ? 'aivb-fail' : ( 'warn' === $criterion['status'] ? 'aivb-warn' : 'aivb-pass' );
				?>
				<li class="<?php echo esc_attr( $cls ); ?>">
					<span class="aivb-icon"><?php echo esc_html( $icon ); ?></span>
					<?php echo esc_html( $criterion['label'] ); ?>
					<span class="aivb-pts"><?php echo esc_html( $criterion['score'] . '/' . $criterion['max'] ); ?></span>
					<?php if ( ! empty( $criterion['tip'] ) ) : ?>
						<span class="aivb-tip"><?php echo esc_html( $criterion['tip'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="aivb-plugin-credit">
			<small><?php esc_html_e( 'AI Visibility Booster by Akshay Bhimani', 'ai-visibility-booster' ); ?></small>
		</p>
	</div>
	<?php
}
