const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		// Provided by WordPress at runtime (wp.* globals), not bundled.
		settings: {
			'import/core-modules': [
				'@wordpress/api-fetch',
				'@wordpress/components',
				'@wordpress/data',
				'@wordpress/dom-ready',
				'@wordpress/edit-post',
				'@wordpress/editor',
				'@wordpress/element',
				'@wordpress/i18n',
				'@wordpress/plugins',
			],
		},
	},
];
