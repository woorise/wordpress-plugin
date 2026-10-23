import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import save from './save';
import metadata from './block.json';
import icon from './icon';
import { v1_2_3 } from './deprecated/v1.2.3'

const { name } = metadata;

registerBlockType(name, {
	icon,
	edit,
	save,
	deprecated: [
		v1_2_3
	]
});
