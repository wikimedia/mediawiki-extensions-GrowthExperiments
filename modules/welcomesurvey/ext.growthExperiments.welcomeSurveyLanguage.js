$( function () {
	'use strict';
	var ULSTagMultiselectWidget = require( './ext.growthExperiments.ULSTagMultiselectWidget.js' ),
		config = require( './config.json' ),
		shouldUseLanguageInfoOverlay = OO.ui.isMobile() &&
			mw.mobileFrontend.require( 'mobile.startup' ).languageInfoOverlay,
		/** @type {OO.Router} */
		router = require( 'mediawiki.router' ),
		langCodeMap = $.uls.data.getAutonyms(),
		widgetInstance, fieldLayout;

	widgetInstance = new ULSTagMultiselectWidget( {
		placeholder: mw.message( 'welcomesurvey-question-languages-placeholder' ).text(),
		inputPosition: 'outline',
		tagLimit: config.languageMax,
		allowedValues: Object.keys( langCodeMap ),
		allowArbitrary: false,
		allowEditTags: false,
		langCodeMap: langCodeMap
	} );

	if ( shouldUseLanguageInfoOverlay ) {
		widgetInstance.on( 'inputFocus', function () {
			// FIXME: navigate is deprecated but navigateTo doesn't seem to trigger
			// the language searcher overlay.
			router.navigate( '/languages/all' );
		} );
	}
	mw.hook( 'mobileFrontend.languageSearcher.linkClick' ).add( function ( lang ) {
		widgetInstance.addLanguageByCode( lang );
	} );

	fieldLayout = new OO.ui.FieldLayout( widgetInstance, {
		align: 'top',
		classes: [ 'welcomesurvey-languages-uls' ],
		label: mw.message( 'welcomesurvey-question-languages-label' ).text(),
		help: mw.message( 'welcomesurvey-question-languages-help' )
			.params( [ mw.language.convertNumber( config.languageMax ) ] )
			.text(),
		helpInline: true
	} );
	fieldLayout.$element.insertBefore( '.welcomesurvey-mentor-info' );
}() );
