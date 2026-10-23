// edit.js
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';

import icon from './icon';
import EmbedPreview from './embed-preview';
import EmbedControls from './embed-controls';
import EmbedPlaceholder from './embed-placeholder';

const API_BASE = 'https://woorise.com/api/v1/embed/';

const isUrl = ( val ) => {
	try { const u = new URL( val ); return u.protocol === 'http:' || u.protocol === 'https:'; }
	catch { return false; }
};
const looksLikeEmbedId = ( val ) => /^[A-Z0-9]{12,40}$/.test( val.trim() );

const edit = ( { attributes, setAttributes, isSelected } ) => {
	const { embedId, url } = attributes;

	const [ editing, setEditing ] = useState( ! url );
	const [ inputValue, setInputValue ] = useState( embedId || url || '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const blockProps = useBlockProps();

	const resolveById = async ( id ) => {
		setIsLoading( true );
		setError( '' );
		try {
			const res = await fetch( `${ API_BASE }${ encodeURIComponent( id.trim() ) }`, {
				method: 'GET',
				credentials: 'omit',
				headers: { 'Accept': 'application/json' },
			} );
			if ( ! res.ok ) throw new Error( __( 'Could not fetch campaign details. Please verify the Embed ID.', 'woorise' ) );
			const data = await res.json();
			if ( ! data?.url ) throw new Error( __( 'The API response did not include a campaign URL.', 'woorise' ) );
			setAttributes( { embedId: id.trim(), url: data.url } );
			setEditing( false );
		} catch ( e ) {
			setError( e?.message || __( 'Unexpected error. Please try again.', 'woorise' ) );
		} finally {
			setIsLoading( false );
		}
	};

	const onSubmit = ( evt ) => {
		if ( evt ) evt.preventDefault();
		const v = ( inputValue || '' ).trim();
		if ( ! v ) { setError( __( 'Please enter a URL or an Embed ID.', 'woorise' ) ); return; }

		if ( isUrl( v ) ) {
			setAttributes( { url: v, embedId: '' } );
			setEditing( false );
			setError( '' );
			return;
		}

		if ( looksLikeEmbedId( v ) ) { resolveById( v ); return; }

		setError( __( 'This doesn’t look like a valid URL or Embed ID.', 'woorise' ) );
	};

	const switchBackToInput = () => setEditing( true );
	const label = __( 'Embed ID', 'woorise' );

	return (
		<>
			{ ( ! url || editing ) ? (
				<div { ...blockProps }>
					<EmbedPlaceholder
						icon={ icon }
						label={ label }
						isLoading={ isLoading }
						error={ error }
						onSubmit={ onSubmit }
						value={ inputValue }
						onChange={ ( e ) => setInputValue( e.target.value ) }
					/>
				</div>
			) : (
				<>
					<EmbedControls
						showEditButton={ true }
						switchBackToIDInput={ switchBackToInput }
					/>
					<div { ...blockProps }>
						<EmbedPreview
							url={ url }
							isSelected={ isSelected }
							icon={ icon }
							label={ label }
						/>
					</div>
				</>
			) }
		</>
	);
};

export default edit;
