$( function () {
	'use strict';
	var ULSTagMultiselectWidget = require( './ext.growthExperiments.ULSTagMultiselectWidget.js' ),
		config = require( './config.json' ),
		shouldUseLanguageInfoOverlay = OO.ui.isMobile() &&
			mw.mobileFrontend.require( 'mobile.startup' ).languageInfoOverlay,
		/** @type {OO.Router} */
		router = require( 'mediawiki.router' ),
		langCodeMap = $.uls.data.getAutonyms(),
		widgetInstance;

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

	// eslint-disable-next-line no-jquery/no-global-selector
	$( '.welcomesurvey-languages .oo-ui-checkboxMultiselectInputWidget' )
		.css( 'display', 'none' )
		.after( widgetInstance.$element );
}() );
