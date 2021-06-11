import { __ } from '@wordpress/i18n';
import { Placeholder, TextControl } from '@wordpress/components';
import { BlockIcon, useBlockProps } from '@wordpress/block-editor';
import icon from './icon';

export default function Edit( { attributes, setAttributes } ) {
  return (
		<div { ...useBlockProps() }>
			<Placeholder
				icon={ <BlockIcon icon={ icon } showColors /> }
				label={__( 'Woorise Campaign', 'woorise' )}
				className="wp-block-embed"
				instructions={__( 'Enter your campaign\'s URL…', 'woorise' )}
        isColumnLayout={true}
			>
				<TextControl
					value={ attributes.url }
					onChange={ ( val ) => setAttributes( { url: val } ) }
          type="url"
				/>
			</Placeholder>
		</div>
	);
}
