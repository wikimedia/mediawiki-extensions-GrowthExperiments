( function () {
	var attachButton = require( 'ext.growthExperiments.Homepage.QuestionPoster' ),
		dialogTitle = mw.message( 'growthexperiments-homepage-help-dialog-title' ).text();
	attachButton( {
		buttonSelector: '#mw-ge-homepage-help-cta',
		editorInterface: 'homepage_help',
		dialog: {
			name: 'help',
			panelTitleMessages: {
				questionreview: dialogTitle,
				questioncomplete: dialogTitle
			}
		}
	} );
}() );
