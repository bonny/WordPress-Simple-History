module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	plugins: [ 'validate-jsx-nesting' ],
	globals: {
		// Enqueued by WordPress and by our own wp_localize_script calls.
		jQuery: 'readonly',
		simpleHistoryScriptVars: 'readonly',
	},
	rules: {
		// Allow experimental WordPress APIs - we're aware of the stability risk.
		'@wordpress/no-unsafe-wp-apis': 'off',
		// Allow prompt() for simple user input in the event log GUI.
		'no-alert': 'off',
		// Catch JSX that produces invalid HTML nesting, e.g. a <div> inside a <p>.
		'validate-jsx-nesting/no-invalid-jsx-nesting': 'error',
	},
	settings: {
		// nuqs uses subpath exports which the import resolver doesn't understand.
		'import/core-modules': [ 'nuqs/adapters/react' ],
	},
};
