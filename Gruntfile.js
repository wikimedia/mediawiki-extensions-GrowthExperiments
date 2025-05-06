'use strict';

module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' ),
		messageDirs = conf.MessagesDirs.GrowthExperiments,
		messageDirsWithoutApi = messageDirs.filter( ( dir ) => !dir.includes( '/api' ) );

	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				maxWarnings: 0,
				fix: grunt.option( 'fix' )
			},
			all: [
				'.'
			]
		},
		stylelint: {
			options: {
				cache: true
			},
			all: [
				'modules/**/*.{less,vue}',
				'documentation/frontend/{component-demos,components}/**/*.{less,vue}'
			]
		},
		banana: {
			docs: {
				files: {
					src: messageDirs
				}
			},
			translations: {
				files: {
					src: messageDirsWithoutApi
				},
				options: {
					requireCompleteTranslationLanguages: [
						'ar',
						'cs',
						'eu',
						'fa',
						'fr',
						'hu',
						'hy',
						'ko',
						'sr',
						'uk',
						'vi'
					]
				}
			}
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana:docs', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
