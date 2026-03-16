const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const plugins = ( defaultConfig.plugins || [] ).filter(
	( plugin ) => plugin?.constructor?.name !== 'CleanWebpackPlugin'
);

module.exports = {
	...defaultConfig,
	plugins,
	entry: {
		// Admin React app
		'admin/build/index': path.resolve( __dirname, 'admin/src/index.js' ),
		// Public JS bundles
		'public/js/build/popup': path.resolve(
			__dirname,
			'public/js/src/popup.js'
		),
		'public/js/build/cart-upsell': path.resolve(
			__dirname,
			'public/js/src/cart-upsell.js'
		),
		'public/js/build/tier-table': path.resolve(
			__dirname,
			'public/js/src/tier-table.js'
		),
	},
	output: {
		...( defaultConfig.output || {} ),
		path: path.resolve( __dirname ),
		filename: '[name].js',
		clean: false,
	},
};
