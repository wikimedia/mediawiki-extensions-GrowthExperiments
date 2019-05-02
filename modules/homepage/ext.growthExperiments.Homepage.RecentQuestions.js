( function () {
	var helpStorageKey = 'homepage-help',
		mentorStorageKey = 'homepage-mentor',
		questionStorageKeys = [ helpStorageKey, mentorStorageKey ];

	function updateQuestionsCountInModuleActionData( moduleName, unarchivedCount, archivedCount ) {
		var key = 'wgGEHomepageModuleActionData-' + moduleName,
			moduleActionData = mw.config.get( key ) || {};
		moduleActionData.unarchivedQuestions = unarchivedCount;
		moduleActionData.archivedQuestions = archivedCount;
		mw.config.set( key, moduleActionData );
	}

	function updateRecentQuestions( source ) {
		var sourceName = source === helpStorageKey ? 'help' : 'mentor',
			moduleName = source === helpStorageKey ? 'help' : 'mentorship',
			storage = 'growthexperiments-' + sourceName + '-questions',
			$container = $( '.growthexperiments-homepage-module-' + moduleName ),
			questionsClass = 'recent-questions-growthexperiments-' + sourceName + '-questions',
			archivedCount = 0,
			unarchivedCount = 0;

		new mw.Api().get( {
			action: 'homepagequestionstore',
			storage: storage,
			formatversion: 2
		} )
			.done( function ( data ) {
				var $list = $container.find( '.' + questionsClass + '-list' );
				if ( $list.length && data.homepagequestionstore.html.length ) {
					$list.replaceWith( data.homepagequestionstore.html );
				} else if ( data.homepagequestionstore.html.length ) {
					$container.append(
						$( '<div>' )
							.addClass( questionsClass )
							.append(
								$( '<h3>' ).text( mw.msg( 'growthexperiments-homepage-recent-questions-header' ) ),
								data.homepagequestionstore.html
							)
					);
				} else {
					$container.find( '.' + questionsClass ).remove();
				}
				data.homepagequestionstore.questions.forEach( function ( questionRecord ) {
					if ( questionRecord.isArchived ) {
						archivedCount++;
					} else {
						unarchivedCount++;
					}
				} );
				updateQuestionsCountInModuleActionData(
					moduleName,
					unarchivedCount,
					archivedCount
				);
			} );
	}

	mw.hook( 'growthExperiments.helpPanelQuestionPosted' ).add( function ( data ) {
		updateRecentQuestions( data.helppanelquestionposter.source );
	} );

	questionStorageKeys.forEach( function ( storageKey ) {
		updateRecentQuestions( storageKey );
	} );
}() );
