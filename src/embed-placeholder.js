import { __ } from '@wordpress/i18n';
import { Button, Placeholder } from '@wordpress/components';
import { BlockIcon } from '@wordpress/block-editor';

const EmbedPlaceholder = ( props ) => {
	const { icon, label, value, onSubmit, onChange } = props;

	return (
		<Placeholder
			icon={ <BlockIcon icon={ icon } showColors /> }
			label={ label }
			className="wp-block-embed"
			instructions={
				__( 'Paste the link of the Woorise campaign you want to embed.', 'woorise' )
			}
		>
			<form onSubmit={ onSubmit }>
				<input
					type="url"
					value={ value || '' }
					className="components-placeholder__input"
					aria-label={ label }
					placeholder={ __( 'Enter your campaign\'s URL…', 'woorise' ) }
					onChange={ onChange }
				/>
				<Button isLarge type="submit">
					{ __( 'Embed', 'woorise' ) }
				</Button>
			</form>
		</Placeholder>
	);
};

export default EmbedPlaceholder;
