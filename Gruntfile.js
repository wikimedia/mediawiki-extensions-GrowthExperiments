module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' ),
		messageDirs = conf.MessagesDirs.GrowthExperiments,
		messageDirsWithoutApi = messageDirs.filter( function ( dir ) {
			return dir.indexOf( '/api' ) === -1;
		} );

	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'**/*.{js,json}',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		stylelint: {
			all: [
				'modules/**/*.less'
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
