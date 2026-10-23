import { useBlockProps } from '@wordpress/block-editor'

const save = ({ attributes }) => {
	const { url } = attributes

	const blockProps = useBlockProps.save();

	if (!url) {
		return null;
	}

	return (
		<figure {...blockProps} >
			<div className="wp-block-embed__wrapper">
				{`\n${url}\n`}
			</div>
		</figure>
	);
};

export default save;
