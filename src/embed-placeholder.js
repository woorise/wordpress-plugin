import { __ } from '@wordpress/i18n';
import { Button, Placeholder, Spinner, Notice } from '@wordpress/components';
import { BlockIcon } from '@wordpress/block-editor';

const EmbedPlaceholder = (props) => {
	const { icon, label, value, onSubmit, onChange, isLoading, error } = props;

	return (
		<Placeholder
			icon={<BlockIcon icon={icon} showColors />}
			label={label}
			className="wp-block-embed"
			instructions={ __('Enter the embed ID of the Woorise campaign you want to display.', 'woorise') }
		>
			<form onSubmit={onSubmit}>
				<input
					type="text"
					value={value || ''}
					className="components-placeholder__input"
					aria-label={label}
					placeholder={ __('Enter a Woorise URL or Embed ID…', 'woorise') }
					onChange={onChange}
				/>
				<Button variant="primary" type="submit" disabled={isLoading}>
					{ isLoading ? __('Creating preview…', 'woorise') : __('Embed', 'woorise') }
				</Button>
				{ isLoading && <Spinner /> }
				{ !!error && (
					<Notice status="error" isDismissible={false}>
						{ error }
					</Notice>
				) }
			</form>
		</Placeholder>
	);
};

export default EmbedPlaceholder;
