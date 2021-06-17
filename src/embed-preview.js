import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import IframeResizer from 'iframe-resizer-react'

class EmbedPreview extends Component {
	constructor() {
		super(...arguments);
		this.hideOverlay = this.hideOverlay.bind(this);
		this.state = {
			interactive: false,
		};
	}

	static getDerivedStateFromProps(nextProps, state) {
		if (!nextProps.isSelected && state.interactive) {
			return { interactive: false };
		}

		return null;
	}

	hideOverlay() {
		this.setState({ interactive: true });
	}

	render() {
		const { interactive } = this.state;

		/* eslint-disable jsx-a11y/no-static-element-interactions */
		const embedWrapper = (
			<div className="wp-block-embed__wrapper">
				<IframeResizer
					src={this.props.url}
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
		
		/* eslint-enable jsx-a11y/no-static-element-interactions */
		return (
			<figure className="wp-block-embed-woorise wp-block-embed">
				{ embedWrapper}
			</figure>
		);
	}
}

export default EmbedPreview;
