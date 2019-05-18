( function () {
	var Logger = require( './ext.growthExperiments.Homepage.Logger.js' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		getModuleState = function ( moduleName ) {
			return mw.config.get( 'wgGEHomepageModuleState-' + moduleName ) || '';
		},
		getModuleActionData = function ( moduleName ) {
			return mw.config.get( 'wgGEHomepageModuleActionData-' + moduleName ) || {};
		},
		handleHover = function ( action ) {
			return function () {
				var $module = $( this ),
					moduleName = $module.data( 'module-name' );
				logger.log( moduleName, 'hover-' + action, getModuleState( moduleName ), getModuleActionData( moduleName ) );
			};
		},
		moduleSelector = '.growthexperiments-homepage-module',
		handleClick = function ( e ) {
			var $link = $( this ),
				$module = $link.closest( moduleSelector ),
				linkId = $link.data( 'link-id' ),
				moduleName = $module.data( 'module-name' );
			logger.log( moduleName, 'link-click', getModuleState( moduleName ),
				$.extend( { linkId: linkId }, getModuleActionData( moduleName ) ) );

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			var $module = $( this ),
				moduleName = $module.data( 'module-name' );
			logger.log( moduleName, 'impression', getModuleState( moduleName ), getModuleActionData( moduleName ) );
		},
		helpStorageKey = 'homepage-help',
		mentorStorageKey = 'homepage-mentor',
		questionStorageKeys = [ helpStorageKey, mentorStorageKey ];

	/* eslint-disable no-jquery/no-event-shorthand */
	$( moduleSelector )
		.mouseenter( handleHover( 'in' ) )
		.mouseleave( handleHover( 'out' ) )
		.on( 'click', '[data-link-id]', handleClick )
		.each( logImpression );
	/* eslint-enable no-jquery/no-event-shorthand */

	function updateRecentQuestions( source ) {
		var sourceName = source === helpStorageKey ? 'help' : 'mentor',
			moduleName = source === helpStorageKey ? 'help' : 'mentorship',
			storage = 'growthexperiments-' + sourceName + '-questions',
			$container = $( '.growthexperiments-homepage-module-' + moduleName ),
			questionsClass = 'recent-questions-growthexperiments-' + sourceName + '-questions',
			moduleActionData = getModuleActionData( moduleName ),
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
				moduleActionData.unarchivedQuestions = unarchivedCount;
				moduleActionData.archivedQuestions = archivedCount;
			} );
	}

	mw.hook( 'growthExperiments.helpPanelQuestionPosted' ).add( function ( data ) {
		updateRecentQuestions( data.helppanelquestionposter.source );
	} );

	questionStorageKeys.forEach( function ( storageKey ) {
		updateRecentQuestions( storageKey );
	} );

}() );
