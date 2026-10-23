import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import IframeResizer from '@iframe-resizer/react'

class EmbedPreview extends Component {
	constructor() {
		super( ...arguments );
		this.hideOverlay = this.hideOverlay.bind( this );
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
		this.setState( { interactive: true } );
	}

	render() {
		const { interactive } = this.state;
		const { url } = this.props;

		// Adding the current page URL as a parameter "u" to the iframe's src.
		const enhancedUrl = new URL( url );
		enhancedUrl.searchParams.append( 'u', window.location.href );

		const embedWrapper = (
			<div className="wp-block-embed__wrapper">
				<IframeResizer
					license="GPLv3"
					src={enhancedUrl.toString()}
					checkOrigin={false}
					style={{ width: '1px', minWidth: '100%' }}
				/>
				{ !interactive && (
					<div
						className="block-library-embed__interactive-overlay"
						onMouseUp={this.hideOverlay}
					/>
				)}
			</div>
		);

		return (
			<figure className="wp-block-embed-woorise wp-block-embed">
				{embedWrapper}
			</figure>
		);
	}
}

export default EmbedPreview;
