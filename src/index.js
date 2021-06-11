import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import icon from './icon';

const { name } = metadata;

registerBlockType( name, {
	icon,
	edit,
});
