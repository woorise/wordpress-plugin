( function () {
	'use strict';

	window.addEventListener(
		'message',
		( event ) => {
			if (
				typeof event.data !== 'string' ||
				! event.data.startsWith( '[iFrameSizer]' )
			) {
				return;
			}

			const id = event.data.slice( 13 ).split( ':' )[ 0 ];
			const iframe = document.getElementById( id );
			if (
				iframe?.matches(
					'iframe.e-woorise, iframe.e-woorise-preview'
				) &&
				event.source !== iframe.contentWindow
			) {
				event.stopImmediatePropagation();
			}
		},
		true
	);
} )();
