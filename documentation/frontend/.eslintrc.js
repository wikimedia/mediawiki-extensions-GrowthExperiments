module.exports = {
	root: true,
	extends: [
		'wikimedia/mediawiki',
		'wikimedia/client-es6'
	],
	rules: {
		'vue/component-name-in-template-casing': [
			'error',
			'kebab-case'
		],
		'vue/no-unsupported-features': [
			'error',
			{
				version: '^3.2.27',
				ignores: []
			}
		]
	},
	overrides: [
		{
			files: [
				'docs/*.cjs'
			],
			extends: [
				'wikimedia/mediawiki',
				'wikimedia/client-es6'
			]
		},
		{
			files: [
				'vite.components.config.js',
				'vitest.config.js'
			],
			extends: [
				'wikimedia/selenium',
				'wikimedia/language/es2020'
			],
			// REVIEW: since eslint is also run from the parent directory, it looks up for installed
			// npm packages in <rootDir>/node_modules rather than <rootDir>/docs/node_modules.
			// Running lint and tests from the docs project requires a "workspace" like setup.
			rules: {
				'node/no-missing-require': [ 'error', {
					allowModules: [ 'vite', '@vitejs/plugin-vue' ]
				} ]
			}
		},
		{
			files: [
				'component-demos/**/*.test.js'
			],
			extends: [
				'wikimedia/language/es6',
				'wikimedia/mediawiki'
			]
		}
	],
	ignorePatterns: [
		'.vitepress/cache/**',
		'dist/*',
		'docs/*.md',
		// REVIEW: lib.js fails to be parsed by eslint, afaik there's no suitable eslint wikimedia
		// configuration for it and the usage of import/export requires babel to be installed
		'component-demos/lib.js'
	],
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module'
	}
};
