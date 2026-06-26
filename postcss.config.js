const wpPostcssPluginsPreset = require( '@wordpress/postcss-plugins-preset' );

module.exports = {
	plugins: [
		// Restore the wp-scripts default PostCSS plugins (autoprefixer + cssnano).
		...wpPostcssPluginsPreset,
		// Scope react-big-calendar CSS under .sh-calendar-wrap to prevent
		// wp-admin global styles from bleeding into the calendar grid.
		require( 'postcss-prefix-selector' )( {
			prefix: '.sh-calendar-wrap',
			transform: ( prefix, selector, prefixedSelector, filePath ) => {
				// Only scope react-big-calendar CSS — leave all other CSS untouched.
				if ( filePath && filePath.includes( 'react-big-calendar' ) ) {
					return prefixedSelector;
				}

				return selector;
			},
		} ),
	],
};
