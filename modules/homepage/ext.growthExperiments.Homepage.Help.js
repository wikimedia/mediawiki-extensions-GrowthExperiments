( function () {
	var attachButton = require( 'ext.growthExperiments.Homepage.QuestionPoster' );
	attachButton( {
		buttonSelector: '#mw-ge-homepage-help-cta',
		dialog: {
			name: 'help'
		}
	} );
}() );
