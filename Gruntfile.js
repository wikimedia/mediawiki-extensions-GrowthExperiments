'use strict';

module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' ),
		messageDirs = conf.MessagesDirs.GrowthExperiments,
		messageDirsWithoutApi = messageDirs.filter( ( dir ) => !dir.includes( '/api' ) );

	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
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

	grunt.registerTask( 'test', [ 'banana:docs' ] );
	grunt.registerTask( 'default', 'test' );
};
