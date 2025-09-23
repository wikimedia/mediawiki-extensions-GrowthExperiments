( function () {

	function updateQuestionsCountInModuleActionData( moduleName, unarchivedCount, archivedCount ) {
		const key = 'wgGEHomepageModuleActionData-' + moduleName,
			moduleActionData = mw.config.get( key ) || {};
		moduleActionData.unarchivedQuestions = unarchivedCount;
		moduleActionData.archivedQuestions = archivedCount;
		mw.config.set( key, moduleActionData );
	}

	/**
	 * @param {string} moduleName The name of the module (impact, help, mentorship, start)
	 * @param {string} questionsSelector The selector to use for finding recent questions in the
	 * module HTML.
	 * @param {string} updatedHtml The updated HTML to store in the homepagemodules config var
	 * for {moduleName}
	 */
	function updateHomepageModuleHtml( moduleName, questionsSelector, updatedHtml ) {
		const moduleData = mw.config.get( 'homepagemodules' );
		if ( !moduleData || !moduleData[ moduleName ] ) {
			return;
		}
		const $moduleHtml = $( moduleData[ moduleName ].overlay );
		$moduleHtml.find( questionsSelector ).replaceWith( updatedHtml );
		moduleData[ moduleName ].overlay = $moduleHtml.prop( 'outerHTML' );
		mw.config.set( 'homepagemodules', moduleData );
	}

	function updateRecentQuestions() {
		const sourceName = 'mentor',
			moduleName = 'mentorship',
			storage = 'growthexperiments-' + sourceName + '-questions',
			$overlay = mw.loader.getState( 'ext.growthExperiments.Homepage.mobile' ) === 'ready' ?
				// eslint-disable-next-line no-jquery/no-global-selector
				$( '.homepage-module-overlay .overlay-content' ) :
				// eslint-disable-next-line no-jquery/no-global-selector
				$( 'body' ),
			questionsSelector = '.recent-questions-growthexperiments-' + sourceName + '-questions',
			$container = $overlay.find( '.growthexperiments-homepage-module-' + moduleName );
		let archivedCount = 0,
			unarchivedCount = 0;

		new mw.Api().get( {
			action: 'homepagequestionstore',
			storage: storage,
			uselang: mw.config.get( 'wgUserLanguage' ),
			formatversion: 2,
		} )
			.then( ( data ) => {
				if ( !data.homepagequestionstore ) {
					return;
				}
				const questionStoreHtml = data.homepagequestionstore.html || '';
				if ( questionStoreHtml.length ) {
					$container.find( questionsSelector )
						.replaceWith( questionStoreHtml );
				} else {
					$container.find( questionsSelector ).remove();
				}
				if ( !data.homepagequestionstore.questions ) {
					return;
				}
				data.homepagequestionstore.questions.forEach( ( questionRecord ) => {
					if ( questionRecord.isArchived ) {
						archivedCount++;
					} else {
						unarchivedCount++;
					}
				} );
				updateQuestionsCountInModuleActionData(
					moduleName,
					unarchivedCount,
					archivedCount,
				);
				updateHomepageModuleHtml( moduleName, questionsSelector, questionStoreHtml );
			} );
	}

	mw.hook( 'growthExperiments.helpPanelQuestionPosted' ).add( () => {
		updateRecentQuestions();
	} );

}() );
