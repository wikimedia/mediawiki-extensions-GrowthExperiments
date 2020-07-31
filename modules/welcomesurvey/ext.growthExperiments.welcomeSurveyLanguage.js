$( function () {
	'use strict';
	var ULSTagMultiselectWidget = require( './ext.growthExperiments.ULSTagMultiselectWidget.js' ),
		config = require( './config.json' ),
		shouldUseLanguageInfoOverlay = OO.ui.isMobile() &&
			mw.mobileFrontend.require( 'mobile.startup' ).languageInfoOverlay,
		allowedLanguageValuesPromise = new mw.Api().get( {
			formatversion: 2,
			meta: 'languageinfo',
			liprop: 'code|autonym|name'
		} ),
		/**
		 * @type {OO.Router}
		 */
		router = require( 'mediawiki.router' );
	allowedLanguageValuesPromise.then( function ( response ) {
		return Object.keys( response.query.languageinfo ).map( function ( key ) {
			return {
				url: '#',
				code: response.query.languageinfo[ key ].code,
				name: response.query.languageinfo[ key ].name
			};
		} );
	} ).then( function ( data ) {
		var langCodeMap = {};
		data.forEach( function ( value ) {
			langCodeMap[ value.code ] = value.name;
		}, langCodeMap );
		return {
			languages: data,
			langCodeMap: langCodeMap
		};
	} ).then( function ( data ) {
		return new ULSTagMultiselectWidget( {
			placeholder: mw.message( 'welcomesurvey-question-languages-placeholder' ).text(),
			inputPosition: 'outline',
			tagLimit: config.languageMax,
			allowedValues: Object.keys( data.langCodeMap ),
			allowArbitrary: false,
			allowEditTags: false,
			languages: data.languages,
			langCodeMap: data.langCodeMap
		} );
	} ).then( function ( widgetInstance ) {
		if ( shouldUseLanguageInfoOverlay ) {
			widgetInstance.on( 'inputFocus', function () {
				// FIXME: navigate is deprecated but navigateTo doesn't seem to trigger
				// the language searcher overlay.
				router.navigate( '/languages/all' );
			} );
		}
		return widgetInstance;
	} ).then( function ( widgetInstance ) {
		mw.hook( 'mobileFrontend.languageSearcher.linkClick' ).add( function ( lang ) {
			widgetInstance.addLanguageByCode( lang );
		} );
		return widgetInstance;
	} ).done( function ( widgetInstance ) {
		new OO.ui.FieldLayout( widgetInstance, {
			align: 'top',
			classes: [ 'welcomesurvey-languages-uls' ],
			label: mw.message( 'welcomesurvey-question-languages-label' ).text(),
			help: mw.message( 'welcomesurvey-question-languages-help' )
				.params( [ mw.language.convertNumber( config.languageMax ) ] )
				.text(),
			helpInline: true
		} ).$element.insertBefore( '.welcomesurvey-mentor-info' );
	} );

}() );
