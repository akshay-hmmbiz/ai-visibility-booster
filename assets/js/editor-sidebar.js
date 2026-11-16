/**
 * AI Visibility Booster – Gutenberg Editor Sidebar
 *
 * Registers a sidebar plugin panel in the block editor that displays
 * the AI Visibility Score and breakdown for the current post, fetched
 * from the REST API endpoint: /wp-json/aivb/v1/score/{post_id}
 *
 * Plugin developed by Akshay Bhimani.
 */

/* global aivbData */

( function ( wp ) {
	'use strict';

	var registerPlugin   = wp.plugins.registerPlugin;
	var PluginSidebar    = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var Fragment         = wp.element.Fragment;
	var createElement    = wp.element.createElement;
	var useState         = wp.element.useState;
	var useEffect        = wp.element.useEffect;
	var useSelect        = wp.data.useSelect;
	var apiFetch         = wp.apiFetch;
	var Spinner          = wp.components.Spinner;
	var Button           = wp.components.Button;
	var __               = wp.i18n.__;

	/**
	 * Score colour helper.
	 *
	 * @param {number} score
	 * @return {string} CSS colour value.
	 */
	function scoreColor( score ) {
		if ( score >= 80 ) { return '#00a32a'; }
		if ( score >= 60 ) { return '#2271b1'; }
		if ( score >= 40 ) { return '#996800'; }
		return '#d63638';
	}

	/**
	 * Status icon helper.
	 *
	 * @param {string} status  'pass' | 'warn' | 'fail'
	 * @return {string}
	 */
	function statusIcon( status ) {
		if ( status === 'pass' ) { return '✓'; }
		if ( status === 'warn' ) { return '⚠'; }
		return '✗';
	}

	/**
	 * Status colour helper.
	 *
	 * @param {string} status
	 * @return {string}
	 */
	function statusColor( status ) {
		if ( status === 'pass' ) { return '#00a32a'; }
		if ( status === 'warn' ) { return '#996800'; }
		return '#d63638';
	}

	/**
	 * AIVisibilityPanel component — renders the score + breakdown.
	 */
	function AIVisibilityPanel() {
		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );

		var postStatus = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'status' );
		}, [] );

		var isSaving = useSelect( function ( select ) {
			return select( 'core/editor' ).isSavingPost();
		}, [] );

		var _useState  = useState( null );
		var scoreData  = _useState[ 0 ];
		var setScore   = _useState[ 1 ];

		var _useLoading  = useState( false );
		var loading      = _useLoading[ 0 ];
		var setLoading   = _useLoading[ 1 ];

		var _useError    = useState( null );
		var error        = _useError[ 0 ];
		var setError     = _useError[ 1 ];

		function fetchScore() {
			if ( ! postId ) { return; }
			setLoading( true );
			setError( null );

			apiFetch( {
				path: '/aivb/v1/score/' + postId,
			} )
				.then( function ( data ) {
					setScore( data );
					setLoading( false );
				} )
				.catch( function () {
					setError( __( 'Could not load score. Save the post first.', 'ai-visibility-booster' ) );
					setLoading( false );
				} );
		}

		// Fetch on mount and after each save.
		useEffect( fetchScore, [ postId ] );

		useEffect(
			function () {
				if ( ! isSaving ) {
					fetchScore();
				}
			},
			[ isSaving ] // eslint-disable-line react-hooks/exhaustive-deps
		);

		var panelContent;

		if ( loading ) {
			panelContent = createElement(
				'div',
				{ style: { padding: '16px', textAlign: 'center' } },
				createElement( Spinner )
			);
		} else if ( error ) {
			panelContent = createElement(
				'div',
				{ style: { padding: '12px', color: '#d63638', fontSize: '12px' } },
				error,
				createElement(
					Button,
					{
						isLink: true,
						onClick: fetchScore,
						style: { display: 'block', marginTop: '8px', fontSize: '12px' },
					},
					__( 'Retry', 'ai-visibility-booster' )
				)
			);
		} else if ( scoreData ) {
			var score     = scoreData.score || 0;
			var label     = scoreData.label || '';
			var breakdown = scoreData.breakdown || {};
			var color     = scoreColor( score );

			var breakdownItems = Object.keys( breakdown ).map( function ( key ) {
				var c = breakdown[ key ];
				return createElement(
					'li',
					{
						key: key,
						style: {
							display:       'flex',
							alignItems:    'flex-start',
							gap:           '6px',
							fontSize:      '12px',
							padding:       '5px 0',
							borderBottom:  '1px solid #f0f0f1',
							flexWrap:      'wrap',
						},
					},
					createElement(
						'span',
						{ style: { color: statusColor( c.status ), width: '14px', flexShrink: 0 } },
						statusIcon( c.status )
					),
					createElement( 'span', { style: { flex: 1 } }, c.label ),
					createElement(
						'span',
						{ style: { color: '#646970', fontSize: '11px', flexShrink: 0 } },
						c.score + '/' + c.max
					),
					c.tip
						? createElement(
							'span',
							{
								style: {
									display:    'block',
									width:      '100%',
									fontSize:   '11px',
									color:      '#996800',
									paddingLeft: '20px',
									fontStyle:  'italic',
								},
							},
							c.tip
						)
						: null
				);
			} );

			panelContent = createElement(
				'div',
				{ style: { padding: '12px 16px' } },
				// Score circle row
				createElement(
					'div',
					{ style: { display: 'flex', alignItems: 'baseline', gap: '4px', marginBottom: '4px' } },
					createElement(
						'span',
						{ style: { fontSize: '36px', fontWeight: '700', color: color, lineHeight: 1 } },
						score
					),
					createElement(
						'span',
						{ style: { fontSize: '14px', color: '#646970' } },
						'/100'
					)
				),
				// Label
				createElement(
					'p',
					{ style: { margin: '0 0 8px', fontWeight: '600', fontSize: '13px', color: color } },
					label
				),
				// Progress bar
				createElement(
					'div',
					{ style: { background: '#f0f0f1', borderRadius: '4px', height: '6px', overflow: 'hidden', marginBottom: '12px' } },
					createElement(
						'div',
						{ style: { height: '100%', width: score + '%', background: color, borderRadius: '4px', transition: 'width .4s ease' } }
					)
				),
				// Breakdown list
				createElement(
					'ul',
					{ style: { margin: 0, padding: 0, listStyle: 'none' } },
					breakdownItems
				),
				// Refresh button
				createElement(
					Button,
					{
						isLink: true,
						onClick: fetchScore,
						style: { marginTop: '10px', fontSize: '11px', color: '#646970' },
					},
					__( '↻ Refresh Score', 'ai-visibility-booster' )
				)
			);
		} else {
			panelContent = createElement(
				'div',
				{ style: { padding: '12px', color: '#646970', fontSize: '12px' } },
				__( 'Save the post to calculate your AI Visibility Score.', 'ai-visibility-booster' )
			);
		}

		return createElement(
			Fragment,
			{},
			createElement(
				PluginSidebarMoreMenuItem,
				{ target: 'aivb-sidebar' },
				__( 'AI Visibility Score', 'ai-visibility-booster' )
			),
			createElement(
				PluginSidebar,
				{
					name:  'aivb-sidebar',
					title: __( 'AI Visibility Score', 'ai-visibility-booster' ),
					icon:  'visibility',
				},
				panelContent
			)
		);
	}

	registerPlugin( 'ai-visibility-booster', {
		render: AIVisibilityPanel,
	} );
} )( window.wp );
