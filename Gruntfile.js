'use strict';

module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' ),
		messageDirs = conf.MessagesDirs.GrowthExperiments;

	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		banana: {
			docs: {
				files: {
					src: messageDirs,
				},
			},
		},
	} );

	grunt.registerTask( 'test', [ 'banana:docs' ] );
	grunt.registerTask( 'default', 'test' );
};
