import { __ } from '@wordpress/i18n';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { BlockControls } from '@wordpress/block-editor';
import { edit } from '@wordpress/icons';

const EmbedControls = ({ showEditButton, switchBackToIDInput }) => (
	<BlockControls>
		<ToolbarGroup>
			{ showEditButton && (
				<ToolbarButton
					className="components-toolbar__control"
					label={ __('Edit URL/ID', 'woorise') }
					icon={ edit }
					onClick={ switchBackToIDInput }
				/>
			) }
		</ToolbarGroup>
	</BlockControls>
);

export default EmbedControls;
