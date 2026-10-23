// embed-options.js
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, Tooltip, Icon } from '@wordpress/components';
import { info } from '@wordpress/icons';

const LabelWithTip = ( { label, tip } ) => (
	<span
		style={ { display: 'inline-flex', alignItems: 'center', gap: '6px' } }
	>
		<span>{ label }</span>
		<Tooltip text={ tip }>
			<span
				aria-label={ __( 'More info', 'woorise' ) }
				style={ {
					cursor: 'help',
					display: 'inline-flex',
					width: '20px',
				} }
			>
				<Icon icon={ info } />
			</span>
		</Tooltip>
	</span>
);

const EmbedOptions = ( { attributes, setAttributes } ) => {
	const { showLoader, scrollToTop, lazyLoad } = attributes;

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Embed Options', 'woorise' ) }
				initialOpen={ true }
			>
				<ToggleControl
					checked={ !! showLoader }
					onChange={ ( val ) =>
						setAttributes( { showLoader: !! val } )
					}
					label={
						<LabelWithTip
							label={ __( 'Show loader', 'woorise' ) }
							tip={ __( 'Show a preloader icon', 'woorise' ) }
						/>
					}
				/>
				<ToggleControl
					checked={ !! scrollToTop }
					onChange={ ( val ) =>
						setAttributes( { scrollToTop: !! val } )
					}
					label={
						<LabelWithTip
							label={ __( 'Scroll to top', 'woorise' ) }
							tip={ __(
								'Helps users scroll to the top on multi-page forms with long questions.',
								'woorise'
							) }
						/>
					}
				/>
				<ToggleControl
					checked={ !! lazyLoad }
					onChange={ ( val ) =>
						setAttributes( { lazyLoad: !! val } )
					}
					label={
						<LabelWithTip
							label={ __( 'Lazy load', 'woorise' ) }
							tip={ __(
								'Enable lazy loading to improve page speed and performance.',
								'woorise'
							) }
						/>
					}
				/>
			</PanelBody>
		</InspectorControls>
	);
};

export default EmbedOptions;
