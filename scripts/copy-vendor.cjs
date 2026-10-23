const fs = require( 'node:fs' );
const path = require( 'node:path' );
const root = path.resolve( __dirname, '..' );
const parent = path.dirname(
	require.resolve( '@iframe-resizer/parent/package.json' )
);
const assets = path.join( root, 'assets/public' );
fs.mkdirSync( assets, { recursive: true } );
for ( const [ source, destination ] of [
	[ 'index.umd.js', 'iframe-resizer.parent.js' ],
	[ 'LICENSE', 'iframe-resizer.LICENSE' ],
] ) {
	fs.copyFileSync(
		path.join( parent, source ),
		path.join( assets, destination )
	);
}
