const fs = require( 'node:fs' );
const path = require( 'node:path' );
const root = path.resolve( __dirname, '..' );
const target = process.argv[ 2 ];
if ( ! target || ! path.isAbsolute( target ) ) {
	throw new Error(
		'Supply the absolute path to the existing Woorise plugin directory.'
	);
}
const plugin = fs.readFileSync( path.join( target, 'woorise.php' ), 'utf8' );
if ( ! /Plugin Name: Woorise\r?\n/.test( plugin ) ) {
	throw new Error( 'The destination must be the Woorise plugin directory.' );
}
// PHP, translations, and release metadata are maintained in the plugin directory.
for ( const directory of [ 'src', 'build', 'assets/admin', 'assets/public' ] ) {
	fs.mkdirSync( path.join( target, directory ), { recursive: true } );
	for ( const item of fs.readdirSync( path.join( root, directory ), {
		withFileTypes: true,
	} ) ) {
		if ( item.isFile() ) {
			fs.copyFileSync(
				path.join( root, directory, item.name ),
				path.join( target, directory, item.name )
			);
		}
	}
}
process.stdout.write( 'Synced editor and assets to ' + target + '\n' );
