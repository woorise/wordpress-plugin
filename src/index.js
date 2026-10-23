import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import save from './save';
import metadata from './block.json';
import icon from './icon';

const { name } = metadata;

registerBlockType(name, {
	icon,
	edit,
	save
});
