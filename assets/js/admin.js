/**
 * AI Visibility Booster – Admin JavaScript
 *
 * Handles: bulk-generate-summaries AJAX button.
 *
 * Plugin developed by Akshay Bhimani.
 */

/* global aivbAdmin */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.getElementById( 'aivb-bulk-generate' );
		if ( ! btn ) {
			return;
		}

		var statusEl = document.getElementById( 'aivb-bulk-status' );

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = aivbAdmin.strings.generating;
			if ( statusEl ) {
				statusEl.textContent = '';
			}

			var data = new FormData();
			data.append( 'action', 'aivb_bulk_generate' );
			data.append( 'nonce', aivbAdmin.bulkGenerateNonce );

			fetch( aivbAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					btn.disabled = false;
					btn.textContent = aivbAdmin.strings.generate;

					if ( json && json.success && json.data ) {
						if ( statusEl ) {
							statusEl.textContent = json.data.message;
							statusEl.style.color = '#00a32a';
						}
					} else {
						var msg =
							json && json.data && json.data.message
								? json.data.message
								: aivbAdmin.strings.error;
						if ( statusEl ) {
							statusEl.textContent = msg;
							statusEl.style.color = '#d63638';
						}
					}
				} )
				.catch( function () {
					btn.disabled = false;
					btn.textContent = aivbAdmin.strings.generate;
					if ( statusEl ) {
						statusEl.textContent = aivbAdmin.strings.error;
						statusEl.style.color = '#d63638';
					}
				} );
		} );
	} );
} )();
