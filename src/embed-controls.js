import { __ } from '@wordpress/i18n';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { pencil } from '@wordpress/icons';
import { BlockControls } from '@wordpress/block-editor';

const EmbedControls = ( { showEditButton, switchBackToIDInput } ) => (
	<BlockControls>
		<ToolbarGroup>
			{ showEditButton && (
				<ToolbarButton
					className="components-toolbar__control"
					label={ __( 'Edit URL/ID', 'woorise' ) }
					icon={ pencil }
					onClick={ switchBackToIDInput }
				/>
			) }
		</ToolbarGroup>
	</BlockControls>
);

export default EmbedControls;
