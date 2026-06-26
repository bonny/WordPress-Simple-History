module.exports = {
	plugins: [
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
