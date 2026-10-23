/* global ajaxurl */
( function () {
	'use strict';

	document.addEventListener( 'click', ( event ) => {
		if ( ! event.target.closest( '.woorise-embed-tip .notice-dismiss' ) ) {
			return;
		}

		const body = new URLSearchParams( {
			action: 'woorise_dismiss_embed_notice',
			nonce: window.WooriseEmbedNotice.nonce,
		} );
		fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type':
					'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} ).catch( () => {
			// WordPress has already dismissed the notice for the current page.
		} );
	} );
} )();
