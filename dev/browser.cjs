/* Native-browser regressions with intercepted campaign/API requests. */
const fs = require( 'fs' );
const path = require( 'path' );
const assert = require( 'assert/strict' );
const { chromium } = require( 'playwright' );
if ( ! process.env.WOORISE_PLUGIN_PATH ) {
	throw new Error( 'Set WOORISE_PLUGIN_PATH to the final plugin directory.' );
}
const root = path.resolve( process.env.WOORISE_PLUGIN_PATH );
const wordpress = process.env.WORDPRESS_PATH;
const read = ( file ) => fs.readFileSync( path.join( root, file ), 'utf8' );
const react = fs.readFileSync( path.join( wordpress, 'wp-includes/js/dist/vendor/react.js' ), 'utf8' );
const reactDOM = fs.readFileSync( path.join( wordpress, 'wp-includes/js/dist/vendor/react-dom.js' ), 'utf8' );
const jsxRuntime = fs.readFileSync( path.join( wordpress, 'wp-includes/js/dist/vendor/react-jsx-runtime.js' ), 'utf8' );

const platform = `
window.wp = {
	element: { ...React, createRoot: ReactDOM.createRoot },
	i18n: { __: ( text ) => text },
	blocks: { registerBlockType: ( name, settings ) => { window.embedBlock = settings; } },
	blockEditor: {
		useBlockProps: Object.assign( () => ( { className: 'wp-block-woorise-embed' } ), { save: () => ( { className: 'wp-block-woorise-embed' } ) } ),
		BlockIcon: () => null,
		BlockControls: ( { children } ) => React.createElement( 'div', null, children ),
	},
	components: {},
};
const h = React.createElement;
wp.components.Button = ( { children, onClick, type = 'button', disabled, className, ...rest } ) => h( 'button', { type, disabled, onClick, className, 'aria-label': rest[ 'aria-label' ] }, children );
wp.components.SelectControl = ( { label, value, options, onChange, disabled } ) => h( 'label', null, label, h( 'select', { value, disabled, onChange: ( event ) => onChange( event.target.value ) }, options.map( ( item ) => h( 'option', { key: item.value, value: item.value }, item.label ) ) ) );
wp.components.TextControl = ( { label, value, onChange, placeholder } ) => h( 'label', null, label, h( 'input', { value, placeholder, onChange: ( event ) => onChange( event.target.value ) } ) );
wp.components.Card = wp.components.CardBody = wp.components.ToolbarGroup = ( { children } ) => h( 'div', null, children );
wp.components.ToolbarButton = ( { label, onClick } ) => h( 'button', { onClick }, label );
wp.components.Placeholder = ( { children, label, instructions } ) => h( 'section', null, h( 'h2', null, label ), h( 'p', null, instructions ), children );
wp.components.Notice = ( { children } ) => h( 'p', { role: 'alert' }, children );
wp.components.Spinner = () => h( 'span', { role: 'status' }, 'Loading' );
wp.components.SVG = ( { children, ...props } ) => h( 'svg', props, children );
wp.components.Path = ( props ) => h( 'path', props );
wp.primitives = { SVG: wp.components.SVG, Path: wp.components.Path };
window.WooriseEmbedSettings = { trustedOrigins: [] };
`;

( async () => {
	const browser = await chromium.launch( { headless: true, executablePath: process.env.CHROME_PATH } );
	try {
		const page = await browser.newPage();
		const errors = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );
		const apiIDs = [];
		await page.route( '**/*', async ( route ) => {
			const url = new URL( route.request().url() );
			if ( url.hostname === 'woorise.com' && url.pathname.startsWith( '/api/v1/embed/' ) ) {
				apiIDs.push( url.pathname.split( '/' ).pop() );
				return route.fulfill( { contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: JSON.stringify( { url: 'https://mapped.example/form' } ) } );
			}
			if ( url.hostname === 'redirect.example' ) {
				return route.fulfill( { contentType: 'text/html', body: '<!doctype html><script>location.replace("https://mapped.example/form");</script>' } );
			}
			return route.fulfill( { contentType: 'text/html', body: '<!doctype html><html lang="en"><head><title>Fixture</title></head><body><main><div id="root"></div></main></body></html>' } );
		} );

		// Exercise the actual shipped parent library across separate browser origins.
		await page.goto( 'https://parent.example/' );
		await page.evaluate( () => {
			document.querySelector( 'main' ).innerHTML = '<iframe class="e-woorise" title="Woorise campaign" src="https://mapped.example/form" data-wr-origins=\'["https://mapped.example"]\'></iframe><iframe title="Unrelated frame" sandbox="allow-scripts" src="https://evil.example/"></iframe><iframe title="Other mapped campaign" src="https://mapped.example/other"></iframe>';
		} );
		await page.addScriptTag( { content: read( 'assets/public/iframe-messages.js' ) } );
		await page.addScriptTag( { content: read( 'assets/public/iframe-resizer.parent.js' ) } );
		await page.addScriptTag( { content: read( 'assets/public/iframe-init.js' ) } );
		await page.waitForFunction( () => document.querySelector( '.e-woorise' ).iframeResizer );
		const id = await page.locator( '.e-woorise' ).getAttribute( 'id' );
		const mapped = page.frames().find( ( frame ) => frame.url().startsWith( 'https://mapped.example/' ) );
		await mapped.evaluate( ( frameID ) => parent.postMessage( '[iFrameSizer]' + frameID + ':350:500:resize:5.5.9:0', '*' ), id );
		await page.waitForFunction( () => document.querySelector( '.e-woorise' ).style.height === '350px' );
		const evil = page.frames().find( ( frame ) => frame.url().startsWith( 'https://evil.example/' ) );
		await evil.evaluate( ( frameID ) => parent.postMessage( '[iFrameSizer]' + frameID + ':9999:500:resize:5.5.9:0', '*' ), id );
		await page.waitForTimeout( 150 );
		assert.equal( await page.locator( '.e-woorise' ).evaluate( ( iframe ) => iframe.style.height ), '350px', 'Unrelated origin resized the campaign.' );
		const sibling = page.frames().find( ( frame ) => frame.url() === 'https://mapped.example/other' );
		await sibling.evaluate( ( frameID ) => parent.postMessage( '[iFrameSizer]' + frameID + ':9999:500:resize:5.5.9:0', '*' ), id );
		await page.waitForTimeout( 150 );
		assert.equal( await page.locator( '.e-woorise' ).evaluate( ( iframe ) => iframe.style.height ), '350px', 'Same-origin sibling resized another campaign.' );

		// An explicitly trusted destination continues working after a domain redirect.
		await page.goto( 'https://parent.example/' );
		await page.evaluate( () => {
			document.querySelector( 'main' ).innerHTML = '<iframe class="e-woorise" title="Woorise campaign" src="https://redirect.example/form" data-wr-origins=\'["https://redirect.example","https://mapped.example"]\'></iframe>';
		} );
		await page.addScriptTag( { content: read( 'assets/public/iframe-messages.js' ) } );
		await page.addScriptTag( { content: read( 'assets/public/iframe-resizer.parent.js' ) } );
		await page.addScriptTag( { content: read( 'assets/public/iframe-init.js' ) } );
		await page.waitForFunction( () => document.querySelector( '.e-woorise' ).iframeResizer );
		const redirected = await ( await page.locator( '.e-woorise' ).elementHandle() ).contentFrame();
		await redirected.waitForURL( 'https://mapped.example/form' );
		const redirectedID = await page.locator( '.e-woorise' ).getAttribute( 'id' );
		await redirected.evaluate( ( frameID ) => parent.postMessage( '[iFrameSizer]' + frameID + ':420:500:resize:5.5.9:0', '*' ), redirectedID );
		await page.waitForFunction( () => document.querySelector( '.e-woorise' ).style.height === '420px' );

		// Load the actual distributed editor bundle with platform component fixtures.
		await page.goto( 'https://parent.example/wp-admin/post.php?post=42&_wpnonce=PRIVATE' );
		await page.addScriptTag( { content: react } );
		await page.addScriptTag( { content: reactDOM } );
		await page.addScriptTag( { content: jsxRuntime } );
		await page.addScriptTag( { content: platform } );
		await page.addScriptTag( { content: read( 'assets/public/iframe-messages.js' ) } );
		await page.addScriptTag( { content: read( 'build/index.js' ) } );
		await page.evaluate( () => {
			function EditorFixture() {
				const [ attributes, setAttributes ] = React.useState( {} );
				return React.createElement( embedBlock.edit, { attributes, isSelected: true, setAttributes: ( next ) => setAttributes( ( previous ) => ( { ...previous, ...next } ) ) } );
			}
			wp.element.createRoot( document.getElementById( 'root' ) ).render( React.createElement( EditorFixture ) );
		} );
		await page.getByRole( 'textbox', { name: 'Embed ID', exact: true } ).fill( 'abc123456' );
		await page.getByRole( 'button', { name: 'Embed', exact: true } ).click();
		const preview = page.getByTitle( 'Woorise campaign preview' );
		await preview.waitFor();
		assert.deepEqual( apiIDs, [ 'ABC123456' ] );
		const previewURL = new URL( await preview.getAttribute( 'src' ) );
		assert.equal( previewURL.hostname, 'mapped.example' );
		assert.equal( previewURL.searchParams.get( 'u' ), 'https://parent.example' );
		assert.equal( previewURL.searchParams.get( 'woorise-source' ), 'https://parent.example' );
		assert.equal( previewURL.searchParams.get( 'woorise-embed-type' ), 'standard' );
		assert.equal( previewURL.href.includes( 'PRIVATE' ), false );
		await page.getByRole( 'button', { name: 'Interact with campaign preview' } ).press( 'Enter' );
		await page.waitForFunction( () => document.activeElement.tagName === 'IFRAME' );
		assert.equal( await preview.getAttribute( 'tabindex' ), '0' );
		await page.addScriptTag( { content: fs.readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' ) } );
		const accessibility = await page.evaluate( () => axe.run( document, { runOnly: { type: 'rule', values: [ 'frame-title', 'button-name', 'label' ] } } ) );
		assert.deepEqual( accessibility.violations.map( ( issue ) => issue.id ), [] );

		// Exercise normal typing in the actual conditions editor, including commas.
		await page.goto( 'https://parent.example/wp-admin/post.php?post=42' );
		await page.addScriptTag( { content: react } );
		await page.addScriptTag( { content: reactDOM } );
		await page.addScriptTag( { content: platform } );
		await page.evaluate( () => {
			window.WooriseConditions = { fields: [ { key: 'page', type: 'ids', label: 'Pages' } ], operators: { ids: { in: 'In', not_in: 'Not in' } }, cpts: [] };
			const initial = [ [ { field: 'page', operator: 'in', value: [ 12 ] } ] ];
			document.querySelector( 'main' ).innerHTML = '<form id="post"><div id="wr-cond-react"></div><input type="hidden" id="woorise_rules" name="woorise_rules"></form>';
			document.getElementById( 'wr-cond-react' ).dataset.initial = JSON.stringify( initial );
		} );
		await page.addScriptTag( { content: read( 'assets/admin/embed-settings.js' ) } );
		const ids = page.getByRole( 'textbox', { name: 'Specific pages (IDs)' } );
		await ids.press( 'End' );
		await ids.pressSequentially( ', 45, 90' );
		assert.equal( await ids.inputValue(), '12, 45, 90' );
		await page.waitForFunction( () => JSON.parse( document.getElementById( 'woorise_rules' ).value )[0][0].value.length === 3 );
		assert.deepEqual( await page.locator( '#woorise_rules' ).evaluate( ( input ) => JSON.parse( input.value )[0][0].value ), [ 12, 45, 90 ] );
		assert.deepEqual( errors, [] );
		console.log( 'PASS: native-browser mapped origins, blocked forgery, trusted redirects, built editor, nine-character ID resolution, keyboard preview focus, targeted accessibility rules, and page-ID typing.' );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => { console.error( error ); process.exitCode = 1; } );
