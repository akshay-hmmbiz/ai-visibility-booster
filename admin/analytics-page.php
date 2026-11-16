<?php
/**
 * AI Visibility Booster – Bot Analytics Dashboard Page
 *
 * Renders the AI Bot Analytics admin page: summary cards, daily bar chart,
 * top bots table, and a recent activity log.
 *
 * @package AI_Visibility_Booster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Page Renderer ────────────────────────────────────────────────────────────

/**
 * Render the Bot Analytics admin page.
 */
function aivb_render_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Date-range selector (GET param, sanitised).
	$allowed_days = array( 7, 14, 30, 90, 0 );
	$days_raw     = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$days         = in_array( $days_raw, $allowed_days, true ) ? $days_raw : 30;
	$chart_days   = ( 0 === $days || $days > 14 ) ? 14 : $days;

	// ── Gather data ──────────────────────────────────────────────────────────
	$stats        = aivb_get_analytics_stats( $days );
	$top_bots     = aivb_get_top_bots( ( 0 === $days ) ? 365 : $days, 10 );
	$daily_totals = aivb_get_daily_totals( $chart_days );
	$recent       = aivb_get_recent_visits( 25 );

	// Build a date-indexed lookup for the bar chart.
	$daily_map = array();
	foreach ( $daily_totals as $row ) {
		$daily_map[ $row->visit_date ] = (int) $row->visits;
	}

	// Fill in every day so the chart has no gaps.
	$chart_labels = array();
	$chart_data   = array();
	for ( $i = $chart_days - 1; $i >= 0; $i-- ) {
		$date            = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
		$chart_labels[]  = gmdate( 'M j', strtotime( $date ) );
		$chart_data[]    = isset( $daily_map[ $date ] ) ? $daily_map[ $date ] : 0;
	}
	$max_daily = max( array_merge( array( 1 ), $chart_data ) );

	// Range labels.
	$range_label = array(
		7  => __( '7 Days',  'ai-visibility-booster' ),
		14 => __( '14 Days', 'ai-visibility-booster' ),
		30 => __( '30 Days', 'ai-visibility-booster' ),
		90 => __( '90 Days', 'ai-visibility-booster' ),
		0  => __( 'All Time', 'ai-visibility-booster' ),
	);

	$base_url = admin_url( 'admin.php?page=aivb-analytics' );

	?>
	<div class="wrap aivb-dashboard-wrap">

		<!-- ── Page Header ── -->
		<h1 class="aivb-page-title">
			<span class="dashicons dashicons-chart-bar"></span>
			<?php esc_html_e( 'AI Bot Analytics', 'ai-visibility-booster' ); ?>
			<span class="aivb-version">v<?php echo esc_html( AIVB_VERSION ); ?></span>
		</h1>
		<p class="aivb-tagline">
			<?php esc_html_e( 'Real-time insights into AI crawler and citation bot activity on your website.', 'ai-visibility-booster' ); ?>
		</p>

		<!-- ── Date Range Tabs ── -->
		<div class="aivb-range-tabs">
			<?php foreach ( $range_label as $d => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'days', $d, $base_url ) ); ?>"
				   class="aivb-range-tab <?php echo ( $days === $d ) ? 'aivb-range-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- ── Summary Stat Cards ── -->
		<div class="aivb-analytics-stats-row">

			<div class="aivb-stat-card">
				<div class="aivb-stat-number"><?php echo esc_html( number_format( $stats['total_visits'] ) ); ?></div>
				<div class="aivb-stat-label"><?php esc_html_e( 'Total Bot Visits', 'ai-visibility-booster' ); ?></div>
				<div class="aivb-stat-sub"><?php echo esc_html( number_format( $stats['today_visits'] ) ); ?> <?php esc_html_e( 'today', 'ai-visibility-booster' ); ?></div>
			</div>

			<div class="aivb-stat-card">
				<div class="aivb-stat-number"><?php echo esc_html( number_format( $stats['unique_pages'] ) ); ?></div>
				<div class="aivb-stat-label"><?php esc_html_e( 'Pages Crawled', 'ai-visibility-booster' ); ?></div>
				<div class="aivb-stat-sub"><?php echo esc_html( number_format( $stats['unique_bots'] ) ); ?> <?php esc_html_e( 'unique bot types', 'ai-visibility-booster' ); ?></div>
			</div>

			<div class="aivb-stat-card">
				<div class="aivb-stat-number aivb-stat-green"><?php echo esc_html( number_format( $stats['total_citations'] ) ); ?></div>
				<div class="aivb-stat-label"><?php esc_html_e( 'AI Citations', 'ai-visibility-booster' ); ?></div>
				<div class="aivb-stat-sub"><?php esc_html_e( 'Content attribution visits', 'ai-visibility-booster' ); ?></div>
			</div>

			<div class="aivb-stat-card">
				<div class="aivb-stat-number"><?php echo esc_html( number_format( $stats['total_sessions'] ) ); ?></div>
				<div class="aivb-stat-label"><?php esc_html_e( 'Crawl Sessions', 'ai-visibility-booster' ); ?></div>
				<div class="aivb-stat-sub"><?php esc_html_e( '30-minute session windows', 'ai-visibility-booster' ); ?></div>
			</div>

			<div class="aivb-stat-card">
				<div class="aivb-stat-number"><?php echo esc_html( $stats['total_visits'] > 0 ? number_format( $stats['unique_pages'] / max( $stats['total_sessions'], 1 ), 1 ) : '0' ); ?></div>
				<div class="aivb-stat-label"><?php esc_html_e( 'Avg. Pages / Session', 'ai-visibility-booster' ); ?></div>
				<div class="aivb-stat-sub"><?php esc_html_e( 'Depth of crawl sessions', 'ai-visibility-booster' ); ?></div>
			</div>

		</div><!-- /.aivb-analytics-stats-row -->

		<!-- ── Two-column layout: Chart + Top Bots ── -->
		<div class="aivb-analytics-two-col">

			<!-- Daily Activity Bar Chart -->
			<div class="aivb-analytics-panel aivb-chart-panel">
				<h2><?php
					printf(
						/* translators: %d: number of days shown in the chart */
						esc_html__( 'Daily Bot Activity — Last %d Days', 'ai-visibility-booster' ),
						(int) $chart_days
					);
				?></h2>
				<div class="aivb-bar-chart">
					<?php foreach ( $chart_data as $idx => $count ) :
						$pct = ( 0 === $max_daily ) ? 0 : round( $count / $max_daily * 100 );
						?>
						<div class="aivb-bar-col">
							<div class="aivb-bar-wrap">
								<div class="aivb-bar" style="height: <?php echo (int) $pct; ?>%;" title="<?php echo esc_attr( $chart_labels[ $idx ] . ': ' . $count . ' visits' ); ?>">
									<?php if ( $count > 0 ) : ?>
										<span class="aivb-bar-val"><?php echo esc_html( $count ); ?></span>
									<?php endif; ?>
								</div>
							</div>
							<div class="aivb-bar-label"><?php echo esc_html( $chart_labels[ $idx ] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div><!-- /.aivb-bar-chart -->
				<?php if ( empty( array_filter( $chart_data ) ) ) : ?>
					<p class="aivb-no-data">
						<?php esc_html_e( 'No bot visits recorded yet. Data will appear here as AI crawlers visit your site.', 'ai-visibility-booster' ); ?>
					</p>
				<?php endif; ?>
			</div><!-- /.aivb-chart-panel -->

			<!-- Top Bots Panel -->
			<div class="aivb-analytics-panel">
				<h2><?php esc_html_e( 'Top Bots by Activity', 'ai-visibility-booster' ); ?></h2>

				<?php if ( empty( $top_bots ) ) : ?>
					<p class="aivb-no-data"><?php esc_html_e( 'No data for this period.', 'ai-visibility-booster' ); ?></p>
				<?php else : ?>
					<div class="aivb-table-wrap">
						<table class="aivb-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Bot', 'ai-visibility-booster' ); ?></th>
									<th><?php esc_html_e( 'Type', 'ai-visibility-booster' ); ?></th>
									<th><?php esc_html_e( 'Visits', 'ai-visibility-booster' ); ?></th>
									<th><?php esc_html_e( 'Pages', 'ai-visibility-booster' ); ?></th>
									<th><?php esc_html_e( 'Sessions', 'ai-visibility-booster' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$max_visits = ! empty( $top_bots ) ? (int) $top_bots[0]->total_visits : 1;
								foreach ( $top_bots as $bot ) :
									$type_class = ( 'citation' === $bot->bot_type ) ? 'aivb-badge-citation' : 'aivb-badge-crawl';
									$type_label = ( 'citation' === $bot->bot_type )
										? __( 'Citation', 'ai-visibility-booster' )
										: __( 'Crawl', 'ai-visibility-booster' );
									$bar_pct = $max_visits ? round( (int) $bot->total_visits / $max_visits * 100 ) : 0;
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $bot->bot_name ); ?></strong>
											<div class="aivb-bot-company"><?php echo esc_html( $bot->bot_company ); ?></div>
											<div class="aivb-inline-bar">
												<div class="aivb-inline-bar-fill" style="width: <?php echo (int) $bar_pct; ?>%;"></div>
											</div>
										</td>
										<td><span class="aivb-type-badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( $type_label ); ?></span></td>
										<td class="aivb-num"><?php echo esc_html( number_format( (int) $bot->total_visits ) ); ?></td>
										<td class="aivb-num"><?php echo esc_html( number_format( (int) $bot->unique_pages ) ); ?></td>
										<td class="aivb-num"><?php echo esc_html( number_format( (int) $bot->sessions ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div><!-- /.aivb-analytics-panel -->

		</div><!-- /.aivb-analytics-two-col -->

		<!-- ── Recent Activity Log ── -->
		<div class="aivb-analytics-panel aivb-recent-panel">
			<h2><?php esc_html_e( 'Recent Bot Activity', 'ai-visibility-booster' ); ?></h2>

			<?php if ( empty( $recent ) ) : ?>
				<p class="aivb-no-data"><?php esc_html_e( 'No visits logged yet.', 'ai-visibility-booster' ); ?></p>
			<?php else : ?>
				<div class="aivb-table-wrap">
					<table class="aivb-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date & Time', 'ai-visibility-booster' ); ?></th>
								<th><?php esc_html_e( 'Bot', 'ai-visibility-booster' ); ?></th>
								<th><?php esc_html_e( 'Type', 'ai-visibility-booster' ); ?></th>
								<th><?php esc_html_e( 'Page Visited', 'ai-visibility-booster' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent as $visit ) :
								$type_class = ( 'citation' === $visit->bot_type ) ? 'aivb-badge-citation' : 'aivb-badge-crawl';
								$type_label = ( 'citation' === $visit->bot_type )
									? __( 'Citation', 'ai-visibility-booster' )
									: __( 'Crawl', 'ai-visibility-booster' );
								?>
								<tr>
									<td class="aivb-time"><?php echo esc_html( get_date_from_gmt( $visit->visit_time, 'M j, Y g:i a' ) ); ?></td>
									<td><strong><?php echo esc_html( $visit->bot_name ); ?></strong></td>
									<td><span class="aivb-type-badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( $type_label ); ?></span></td>
									<td class="aivb-page-url">
										<a href="<?php echo esc_url( $visit->page_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( rtrim( str_replace( home_url(), '', $visit->page_url ), '/' ) ?: '/' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div><!-- /.aivb-recent-panel -->

		<!-- ── Privacy Notice ── -->
		<div class="aivb-analytics-privacy-notice">
			<span class="dashicons dashicons-shield-alt"></span>
			<?php esc_html_e( 'Privacy: All data is stored locally in your WordPress database. IP addresses are hashed (SHA-256) with a site-specific salt before storage — they are never stored in plain text and cannot be reversed. No data is sent to any external server.', 'ai-visibility-booster' ); ?>
		</div>

		<!-- ── Footer ── -->
		<div class="aivb-info-section">
			<p class="aivb-credit">
				<?php
				printf(
					/* translators: %s: plugin name and version */
					esc_html__( '%s — Plugin by Akshay Bhimani', 'ai-visibility-booster' ),
					'<strong>AI Visibility Booster v' . esc_html( AIVB_VERSION ) . '</strong>'
				);
				?>
			</p>
		</div>

	</div><!-- /.wrap.aivb-dashboard-wrap -->
	<?php
}
