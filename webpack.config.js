/**
 * Webpack configuration for Woo Upsell Pro.
 *
 * Extends @wordpress/scripts default config with multiple entry points:
 *   - Admin React app  → admin/build/
 *   - Public JS bundles → public/js/build/
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Admin React app.
		index: path.resolve( __dirname, 'admin/src/index.js' ),

		// Public feature scripts.
		popup:        path.resolve( __dirname, 'public/js/src/popup.js' ),
		sidecart:     path.resolve( __dirname, 'public/js/src/sidecart.js' ),
		'tier-table': path.resolve( __dirname, 'public/js/src/tier-table.js' ),
		'cart-upsell': path.resolve( __dirname, 'public/js/src/cart-upsell.js' ),
	},
	output: {
		...defaultConfig.output,
		// Each entry writes to its own directory based on entry name prefix.
		// @wordpress/scripts handles asset.php generation automatically.
		filename: ( pathData ) => {
			const name = pathData.chunk.name;
			if ( name === 'index' ) {
				return path.join( '..', 'admin', 'build', '[name].js' );
			}
			return path.join( '..', 'public', 'js', 'build', '[name].js' );
		},
		path: path.resolve( __dirname, 'build' ),
	},
};
