<?php
/**
 * AI Visibility Booster – Admin Dashboard
 *
 * Registers the admin menu, renders the dashboard overview, provides
 * a settings form for enabling/disabling individual features, and
 * handles the AJAX bulk-generate-summaries request.
 *
 * Plugin developed by Akshay Bhimani.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Admin Menu ───────────────────────────────────────────────────────────────

/**
 * Register the top-level admin menu page.
 */
function aivb_register_admin_menu() {
	add_menu_page(
		__( 'AI Visibility Booster', 'ai-visibility-booster' ),
		__( 'AI Visibility', 'ai-visibility-booster' ),
		'manage_options',
		'ai-visibility-booster',
		'aivb_render_dashboard',
		'dashicons-visibility',
		80
	);

	add_submenu_page(
		'ai-visibility-booster',
		__( 'Dashboard', 'ai-visibility-booster' ),
		__( 'Dashboard', 'ai-visibility-booster' ),
		'manage_options',
		'ai-visibility-booster',
		'aivb_render_dashboard'
	);

	add_submenu_page(
		'ai-visibility-booster',
		__( 'Bot Analytics', 'ai-visibility-booster' ),
		__( 'Bot Analytics', 'ai-visibility-booster' ),
		'manage_options',
		'aivb-analytics',
		'aivb_render_analytics_page'
	);
}
add_action( 'admin_menu', 'aivb_register_admin_menu' );

// ─── Settings Save ────────────────────────────────────────────────────────────

/**
 * Handle the settings form POST submission.
 */
function aivb_save_settings() {
	if ( ! isset( $_POST['aivb_settings_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aivb_settings_nonce'] ) ), 'aivb_save_settings' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'ai-visibility-booster' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'ai-visibility-booster' ) );
	}

	$options = array(
		'aivb_enable_sitemap'         => '0',
		'aivb_enable_feed'            => '0',
		'aivb_enable_knowledge_graph' => '0',
		'aivb_enable_schema'          => '0',
		'aivb_enable_markdown'        => '0',
		'aivb_enable_summary'         => '0',
		'aivb_enable_analytics'       => '0',
	);

	foreach ( array_keys( $options ) as $key ) {
		$options[ $key ] = isset( $_POST[ $key ] ) ? '1' : '0';
	}

	foreach ( $options as $key => $value ) {
		update_option( $key, $value );
	}

	// Flush rewrite rules so endpoint availability updates immediately.
	flush_rewrite_rules();

	add_settings_error(
		'aivb_settings',
		'aivb_settings_saved',
		__( 'Settings saved successfully.', 'ai-visibility-booster' ),
		'success'
	);

	// When called through admin-post.php, go back to the dashboard. Without
	// this redirect admin-post.php ends on a blank page.
	if ( doing_action( 'admin_post_aivb_save_settings' ) ) {
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=ai-visibility-booster&settings-updated=true' ) );
		exit;
	}
}
add_action( 'admin_post_aivb_save_settings', 'aivb_save_settings' );

// ─── AJAX: Bulk Generate Summaries ────────────────────────────────────────────

/**
 * AJAX handler: bulk generate AI summaries for all posts/pages that
 * do not yet have one.
 */
function aivb_ajax_bulk_generate() {
	check_ajax_referer( 'aivb_bulk_generate', 'nonce' );

	// Site-wide operation: restrict to the same capability as the dashboard page.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-visibility-booster' ) ) );
	}

	$generated = aivb_bulk_generate_summaries();

	// Bust all caches after bulk operation.
	delete_transient( 'aivb_sitemap_cache' );
	delete_transient( 'aivb_feed_cache' );
	delete_transient( 'aivb_knowledge_cache' );

	wp_send_json_success(
		array(
			/* translators: %d: number of summaries generated */
			'message' => sprintf( __( 'Generated summaries for %d post(s).', 'ai-visibility-booster' ), $generated ),
			'count'   => $generated,
		)
	);
}
add_action( 'wp_ajax_aivb_bulk_generate', 'aivb_ajax_bulk_generate' );

// ─── Dashboard Render ─────────────────────────────────────────────────────────

/**
 * Render the full admin dashboard page.
 */
function aivb_render_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Handle inline settings save (fallback if admin_post fails).
	if ( isset( $_POST['aivb_settings_nonce'] ) ) {
		aivb_save_settings();
	}

	settings_errors( 'aivb_settings' );

	// ── Gather status data ─────────────────────────────────────────────────
	$enable_sitemap   = get_option( 'aivb_enable_sitemap', '1' );
	$enable_feed      = get_option( 'aivb_enable_feed', '1' );
	$enable_knowledge = get_option( 'aivb_enable_knowledge_graph', '1' );
	$enable_schema    = get_option( 'aivb_enable_schema', '1' );
	$enable_markdown  = get_option( 'aivb_enable_markdown', '1' );
	$enable_summary   = get_option( 'aivb_enable_summary', '1' );
	$enable_analytics = get_option( 'aivb_enable_analytics', '1' );

	$sitemap_url   = esc_url( home_url( '/ai-sitemap.xml' ) );
	$feed_url      = esc_url( home_url( '/ai-feed.json' ) );
	$knowledge_url = esc_url( home_url( '/ai-knowledge.json' ) );

	$posts_with_summary = aivb_count_posts_with_summary();
	$avg_score          = aivb_average_visibility_score();
	$avg_label          = aivb_score_label( (int) $avg_score );

	$total_posts = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;

	$score_class = 'aivb-score-poor';
	if ( $avg_score >= 80 ) {
		$score_class = 'aivb-score-excellent';
	} elseif ( $avg_score >= 60 ) {
		$score_class = 'aivb-score-good';
	} elseif ( $avg_score >= 40 ) {
		$score_class = 'aivb-score-moderate';
	}

	?>
	<div class="wrap aivb-dashboard-wrap">

		<h1 class="aivb-page-title">
			<span class="dashicons dashicons-visibility"></span>
			<?php esc_html_e( 'AI Visibility Booster', 'ai-visibility-booster' ); ?>
			<span class="aivb-version">v<?php echo esc_html( AIVB_VERSION ); ?></span>
		</h1>

		<p class="aivb-tagline">
			<?php esc_html_e( 'Help AI systems like ChatGPT, Claude, Gemini, and Perplexity understand and cite your content.', 'ai-visibility-booster' ); ?>
		</p>

		<!-- ── Status Cards ── -->
		<div class="aivb-cards-grid">

			<!-- Avg. Score Card -->
			<div class="aivb-card aivb-card-score">
				<div class="aivb-card-icon dashicons dashicons-chart-line"></div>
				<div class="aivb-card-body">
					<div class="aivb-score-badge <?php echo esc_attr( $score_class ); ?>">
						<?php echo esc_html( number_format( $avg_score, 1 ) ); ?>
						<small>/100</small>
					</div>
					<p class="aivb-card-label"><?php esc_html_e( 'Average AI Visibility Score', 'ai-visibility-booster' ); ?></p>
					<p class="aivb-card-sub"><?php echo esc_html( $avg_label ); ?></p>
				</div>
			</div>

			<!-- Summaries Card -->
			<div class="aivb-card">
				<div class="aivb-card-icon dashicons dashicons-text-page"></div>
				<div class="aivb-card-body">
					<div class="aivb-card-number"><?php echo esc_html( $posts_with_summary ); ?> / <?php echo esc_html( $total_posts ); ?></div>
					<p class="aivb-card-label"><?php esc_html_e( 'Posts with AI Summary', 'ai-visibility-booster' ); ?></p>
					<button type="button" id="aivb-bulk-generate" class="button button-secondary aivb-bulk-btn">
						<?php esc_html_e( 'Bulk Generate Missing Summaries', 'ai-visibility-booster' ); ?>
					</button>
					<span id="aivb-bulk-status" class="aivb-bulk-status"></span>
				</div>
			</div>

			<!-- AI Sitemap Card -->
			<div class="aivb-card">
				<div class="aivb-card-icon dashicons dashicons-networking"></div>
				<div class="aivb-card-body">
					<p class="aivb-card-label"><?php esc_html_e( 'AI Sitemap', 'ai-visibility-booster' ); ?></p>
					<?php if ( '1' === $enable_sitemap ) : ?>
						<span class="aivb-status-badge aivb-status-active"><?php esc_html_e( 'Active', 'ai-visibility-booster' ); ?></span>
						<a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" class="aivb-endpoint-link">
							<?php echo esc_html( $sitemap_url ); ?>
						</a>
					<?php else : ?>
						<span class="aivb-status-badge aivb-status-inactive"><?php esc_html_e( 'Disabled', 'ai-visibility-booster' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<!-- AI Feed Card -->
			<div class="aivb-card">
				<div class="aivb-card-icon dashicons dashicons-rss"></div>
				<div class="aivb-card-body">
					<p class="aivb-card-label"><?php esc_html_e( 'AI Discovery Feed', 'ai-visibility-booster' ); ?></p>
					<?php if ( '1' === $enable_feed ) : ?>
						<span class="aivb-status-badge aivb-status-active"><?php esc_html_e( 'Active', 'ai-visibility-booster' ); ?></span>
						<a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" class="aivb-endpoint-link">
							<?php echo esc_html( $feed_url ); ?>
						</a>
					<?php else : ?>
						<span class="aivb-status-badge aivb-status-inactive"><?php esc_html_e( 'Disabled', 'ai-visibility-booster' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Knowledge Graph Card -->
			<div class="aivb-card">
				<div class="aivb-card-icon dashicons dashicons-share-alt2"></div>
				<div class="aivb-card-body">
					<p class="aivb-card-label"><?php esc_html_e( 'Knowledge Graph', 'ai-visibility-booster' ); ?></p>
					<?php if ( '1' === $enable_knowledge ) : ?>
						<span class="aivb-status-badge aivb-status-active"><?php esc_html_e( 'Active', 'ai-visibility-booster' ); ?></span>
						<a href="<?php echo esc_url( $knowledge_url ); ?>" target="_blank" class="aivb-endpoint-link">
							<?php echo esc_html( $knowledge_url ); ?>
						</a>
					<?php else : ?>
						<span class="aivb-status-badge aivb-status-inactive"><?php esc_html_e( 'Disabled', 'ai-visibility-booster' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Schema Card -->
			<div class="aivb-card">
				<div class="aivb-card-icon dashicons dashicons-list-view"></div>
				<div class="aivb-card-body">
					<p class="aivb-card-label"><?php esc_html_e( 'Structured Data (Schema)', 'ai-visibility-booster' ); ?></p>
					<?php if ( '1' === $enable_schema ) : ?>
						<span class="aivb-status-badge aivb-status-active"><?php esc_html_e( 'Active', 'ai-visibility-booster' ); ?></span>
						<?php if ( aivb_seo_plugin_active() ) : ?>
							<p class="aivb-card-note"><?php esc_html_e( 'SEO plugin detected — Article schema only.', 'ai-visibility-booster' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<span class="aivb-status-badge aivb-status-inactive"><?php esc_html_e( 'Disabled', 'ai-visibility-booster' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Bot Analytics Card -->
			<div class="aivb-card">
				<div class="aivb-card-icon dashicons dashicons-chart-bar"></div>
				<div class="aivb-card-body">
					<p class="aivb-card-label"><?php esc_html_e( 'AI Bot Analytics', 'ai-visibility-booster' ); ?></p>
					<?php if ( '1' === $enable_analytics ) :
						$analytics_stats = aivb_get_analytics_stats( 30 );
						?>
						<span class="aivb-status-badge aivb-status-active"><?php esc_html_e( 'Active', 'ai-visibility-booster' ); ?></span>
						<div class="aivb-card-number" style="margin-top:6px;"><?php echo esc_html( number_format( $analytics_stats['total_visits'] ) ); ?></div>
						<p class="aivb-card-sub">
							<?php
							printf(
								/* translators: %d: today's visits */
								esc_html__( 'Bot visits (last 30 days) · %d today', 'ai-visibility-booster' ),
								(int) $analytics_stats['today_visits']
							);
							?>
						</p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aivb-analytics' ) ); ?>" class="button button-secondary aivb-bulk-btn" style="margin-top:8px;">
							<?php esc_html_e( 'View Full Analytics', 'ai-visibility-booster' ); ?>
						</a>
					<?php else : ?>
						<span class="aivb-status-badge aivb-status-inactive"><?php esc_html_e( 'Disabled', 'ai-visibility-booster' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- /.aivb-cards-grid -->

		<!-- ── Settings Form ── -->
		<div class="aivb-settings-section">
			<h2><?php esc_html_e( 'Feature Settings', 'ai-visibility-booster' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enable or disable individual AI Visibility Booster features. Changes flush rewrite rules immediately.', 'ai-visibility-booster' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aivb_save_settings">
				<?php wp_nonce_field( 'aivb_save_settings', 'aivb_settings_nonce' ); ?>

				<table class="form-table aivb-settings-table" role="presentation">
					<tbody>

						<tr>
							<th scope="row"><?php esc_html_e( 'AI Content Summaries', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_summary" value="1" <?php checked( '1', $enable_summary ); ?>>
									<?php esc_html_e( 'Auto-generate summaries and output <meta name="ai-summary"> in page headers.', 'ai-visibility-booster' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'AI Sitemap', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_sitemap" value="1" <?php checked( '1', $enable_sitemap ); ?>>
									<?php
									printf(
										/* translators: %s: sitemap URL */
										esc_html__( 'Enable AI-friendly XML sitemap at %s', 'ai-visibility-booster' ),
										'<code>' . esc_html( $sitemap_url ) . '</code>'
									);
									?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'AI Discovery Feed', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_feed" value="1" <?php checked( '1', $enable_feed ); ?>>
									<?php
									printf(
										/* translators: %s: feed URL */
										esc_html__( 'Enable JSON discovery feed at %s', 'ai-visibility-booster' ),
										'<code>' . esc_html( $feed_url ) . '</code>'
									);
									?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'AI Knowledge Graph', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_knowledge_graph" value="1" <?php checked( '1', $enable_knowledge ); ?>>
									<?php
									printf(
										/* translators: %s: knowledge graph URL */
										esc_html__( 'Enable knowledge graph JSON at %s', 'ai-visibility-booster' ),
										'<code>' . esc_html( $knowledge_url ) . '</code>'
									);
									?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Structured Data (Schema)', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_schema" value="1" <?php checked( '1', $enable_schema ); ?>>
									<?php esc_html_e( 'Output JSON-LD Article, FAQPage, and Organization schema in page headers.', 'ai-visibility-booster' ); ?>
								</label>
								<?php if ( aivb_seo_plugin_active() ) : ?>
									<p class="description">
										<?php esc_html_e( 'A major SEO plugin is active. Full schema will be limited to Article schema only, to avoid duplication.', 'ai-visibility-booster' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'AI Markdown Export', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_markdown" value="1" <?php checked( '1', $enable_markdown ); ?>>
									<?php esc_html_e( 'Enable per-post Markdown export at /ai-markdown/{post-slug}', 'ai-visibility-booster' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'AI Bot Analytics', 'ai-visibility-booster' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="aivb_enable_analytics" value="1" <?php checked( '1', $enable_analytics ); ?>>
									<?php esc_html_e( 'Track visits from AI crawlers and citation bots. No personal data is collected — IPs are hashed before storage.', 'ai-visibility-booster' ); ?>
								</label>
								<p class="description">
									<?php
									printf(
										/* translators: %s: analytics page link */
										esc_html__( 'View detailed analytics at %s', 'ai-visibility-booster' ),
										'<a href="' . esc_url( admin_url( 'admin.php?page=aivb-analytics' ) ) . '">'
										. esc_html__( 'Bot Analytics', 'ai-visibility-booster' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'ai-visibility-booster' ) ); ?>

			</form>
		</div><!-- /.aivb-settings-section -->

		<!-- ── Plugin Info ── -->
		<div class="aivb-info-section">
			<p class="aivb-credit">
				<?php
				printf(
					/* translators: %s: plugin name link */
					esc_html__( '%s — Plugin by Akshay Bhimani', 'ai-visibility-booster' ),
					'<strong>AI Visibility Booster v' . esc_html( AIVB_VERSION ) . '</strong>'
				);
				?>
			</p>
		</div>

	</div><!-- /.wrap.aivb-dashboard-wrap -->
	<?php
}
