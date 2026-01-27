module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	rules: {
		// Allow experimental WordPress APIs - we're aware of the stability risk.
		'@wordpress/no-unsafe-wp-apis': 'off',
		// Allow prompt() for simple user input in the event log GUI.
		'no-alert': 'off',
	},
	settings: {
		// nuqs uses subpath exports which the import resolver doesn't understand.
		'import/core-modules': [ 'nuqs/adapters/react' ],
	},
};
