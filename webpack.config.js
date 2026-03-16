/**
 * Webpack configuration for Woo Upsell Pro.
 *
 * Standalone multi-config (does not spread @wordpress/scripts defaultConfig entry
 * because it is a dynamic function that scans for a /src dir — incompatible with
 * explicit multi-entry builds targeting different output directories).
 *
 * Builds:
 *   - Public JS bundles  → public/js/build/
 *   - Admin React app    → admin/build/
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const path = require( 'path' );

// Shared config — inherit everything from @wordpress/scripts except entry + output.
const sharedConfig = ( () => {
	const { entry, output, plugins, ...rest } = defaultConfig;
	// Remove the default MiniCssExtractPlugin so we can configure it per-build.
	const filteredPlugins = ( plugins || [] ).filter(
		( p ) => ! ( p instanceof MiniCssExtractPlugin )
	);
	return { ...rest, plugins: filteredPlugins };
} )();

module.exports = [
	// Public feature scripts + master CSS.
	{
		...sharedConfig,
		entry: {
			popup:          path.resolve( __dirname, 'public/js/src/popup.js' ),
			sidecart:       path.resolve( __dirname, 'public/js/src/sidecart.js' ),
			'tier-table':   path.resolve( __dirname, 'public/js/src/tier-table.js' ),
			'cart-upsell':  path.resolve( __dirname, 'public/js/src/cart-upsell.js' ),
			// CSS-only entry — outputs wup-public.css via MiniCssExtractPlugin.
			'wup-public':   path.resolve( __dirname, 'public/css/src/wup-public.scss' ),
		},
		output: {
			path: path.resolve( __dirname, 'public/js/build' ),
			filename: '[name].js',
		},
		plugins: [
			...sharedConfig.plugins,
			new MiniCssExtractPlugin( {
				// Output CSS relative to the project root, not the JS build dir.
				filename: ( pathData ) => {
					if ( pathData.chunk.name === 'wup-public' ) {
						return path.join( '..', '..', 'css', '[name].css' );
					}
					return '[name].css';
				},
			} ),
		],
	},
	// Admin React app.
	{
		...sharedConfig,
		entry: {
			index: path.resolve( __dirname, 'admin/src/index.js' ),
		},
		output: {
			path: path.resolve( __dirname, 'admin/build' ),
			filename: '[name].js',
		},
		plugins: [
			...sharedConfig.plugins,
			new MiniCssExtractPlugin( { filename: '[name].css' } ),
		],
	},
];
