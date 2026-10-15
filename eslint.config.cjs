const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		// Provided by WordPress at runtime (wp.* globals), not bundled.
		settings: {
			'import/core-modules': [
				'@wordpress/components',
				'@wordpress/data',
				'@wordpress/edit-post',
				'@wordpress/editor',
				'@wordpress/element',
				'@wordpress/i18n',
				'@wordpress/plugins',
			],
		},
	},
];
