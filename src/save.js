import { useBlockProps } from '@wordpress/block-editor';

const save = ( { attributes } ) => {
	const { url } = attributes;
	if ( ! url ) {
		return null;
	}
	const blockProps = useBlockProps.save();

	return (
		<figure { ...blockProps }>
			<div className="wp-block-embed__wrapper">{ `\n${ url }\n` }</div>
		</figure>
	);
};

export default save;
