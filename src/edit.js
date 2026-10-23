import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element'
import { useBlockProps } from '@wordpress/block-editor'
import icon from './icon';
import EmbedPreview from './embed-preview';
import EmbedControls from './embed-controls';
import EmbedPlaceholder from './embed-placeholder';

const edit = ({ attributes, setAttributes, isSelected }) => {
	const { url } = attributes

	const [editingUrl, setEditingUrl] = useState(false)
	const [placeholderUrl, setPlaceholderUrl] = useState(url)
	const blockProps = useBlockProps()


	const setUrl = (event) => {
		if (event) {
			event.preventDefault();
		}

		setEditingUrl(false)
		setAttributes({ url: placeholderUrl });
	}

	const switchBackToURLInput = () => {
		setEditingUrl(true)
	}

	const label = __('Campaign URL', 'woorise');

	if (!url || editingUrl) {
		return (
			<div {...blockProps}>
				<EmbedPlaceholder
					icon={icon}
					label={label}
					onSubmit={setUrl}
					value={placeholderUrl}
					onChange={(event) =>
						setPlaceholderUrl(event.target.value)
					}
				/>
			</div>
		)
	}

	return (
		<>
			<EmbedControls
				showEditButton={url}
				switchBackToURLInput={switchBackToURLInput}
			/>
			<div {...blockProps}>
				<EmbedPreview
					url={url}
					isSelected={isSelected}
					icon={icon}
					label={label}
				/>
			</div>
		</>
	);
}

export default edit;