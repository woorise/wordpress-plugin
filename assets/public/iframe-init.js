/* global iframeResize */
( function () {
	'use strict';

	function initialize() {
		if ( typeof window.iframeResize !== 'function' ) {
			return;
		}

		document.querySelectorAll( 'iframe.e-woorise' ).forEach( ( iframe ) => {
			const origins = JSON.parse( iframe.dataset.wrOrigins || '[]' ).map(
				( origin ) => new URL( origin ).origin
			);
			iframeResize(
				{
					checkOrigin: origins.length ? origins : true,
					license: 'GPLv3',
				},
				iframe
			);
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize, {
			once: true,
		} );
	} else {
		initialize();
	}
} )();
