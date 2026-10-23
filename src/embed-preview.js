import { __ } from '@wordpress/i18n';
import { Component, createRef } from '@wordpress/element';
import IframeResizer from '@iframe-resizer/react';

class EmbedPreview extends Component {
	constructor() {
		super( ...arguments );
		this.hideOverlay = this.hideOverlay.bind( this );
		this.preview = createRef();
		this.state = {
			interactive: false,
		};
	}

	static getDerivedStateFromProps( nextProps, state ) {
		if ( ! nextProps.isSelected && state.interactive ) {
			return { interactive: false };
		}

		return null;
	}

	hideOverlay() {
		this.setState( { interactive: true }, () => {
			this.preview.current?.querySelector( 'iframe' )?.focus();
		} );
	}

	render() {
		const { interactive } = this.state;
		const { url } = this.props;

		let enhancedUrl;
		try {
			enhancedUrl = new URL( url );
		} catch {
			return (
				<p role="alert">
					{ __(
						'Enter a valid campaign URL to display its preview.',
						'woorise'
					) }
				</p>
			);
		}
		if (
			! [ 'http:', 'https:' ].includes( enhancedUrl.protocol ) ||
			enhancedUrl.username ||
			enhancedUrl.password
		) {
			return (
				<p role="alert">
					{ __(
						'Enter a valid campaign URL to display its preview.',
						'woorise'
					) }
				</p>
			);
		}
		// The editor preview only needs the parent origin, never admin query parameters.
		enhancedUrl.searchParams.set( 'u', window.location.origin );
		enhancedUrl.searchParams.set(
			'woorise-source',
			window.location.origin
		);
		enhancedUrl.searchParams.set( 'woorise-embed-type', 'standard' );
		const trustedOrigins =
			window.WooriseEmbedSettings?.trustedOrigins || [];
		const origins = [
			enhancedUrl.origin,
			...trustedOrigins.map( ( origin ) => new URL( origin ).origin ),
		];

		const embedWrapper = (
			<div className="wp-block-embed__wrapper" ref={ this.preview }>
				<IframeResizer
					className="e-woorise-preview"
					license="GPLv3"
					src={ enhancedUrl.toString() }
					checkOrigin={ origins }
					title={ __( 'Woorise campaign preview', 'woorise' ) }
					tabIndex={ interactive ? 0 : -1 }
					style={ { width: '1px', minWidth: '100%' } }
				/>
				{ ! interactive && (
					<button
						type="button"
						aria-label={ __(
							'Interact with campaign preview',
							'woorise'
						) }
						className="block-library-embed__interactive-overlay"
						style={ {
							background: 'transparent',
							border: 0,
							cursor: 'pointer',
						} }
						onClick={ this.hideOverlay }
					/>
				) }
			</div>
		);

		return (
			<figure className="wp-block-embed-woorise wp-block-embed">
				{ embedWrapper }
			</figure>
		);
	}
}

export default EmbedPreview;
