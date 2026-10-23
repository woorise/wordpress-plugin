const path = require( 'node:path' );
const config = require( '@wordpress/scripts/config/webpack.config' );
const TerserPlugin = require( 'terser-webpack-plugin' );

module.exports = {
	...config,
	entry: { index: path.resolve( __dirname, 'src/index.js' ) },
	optimization: {
		...config.optimization,
		minimizer: [
			new TerserPlugin( {
				extractComments: true,
				terserOptions: {
					format: { comments: /translators:/i },
					compress: { passes: 2 },
					mangle: { reserved: [ '__', '_n', '_nx', '_x' ] },
				},
			} ),
		],
	},
};
