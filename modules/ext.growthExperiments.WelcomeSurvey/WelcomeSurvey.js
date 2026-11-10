( function () {
	'use strict';

	module.exports = {
		setupLanguageSelector: function () {
			const ULSTagMultiselectWidget = require( './ULSTagMultiselectWidget.js' ),
				langCodeMap = $.uls.data.getAutonyms(),
				languageMax = 10;

			const widgetInstance = new ULSTagMultiselectWidget( {
				placeholder: mw.message( 'welcomesurvey-question-languages-placeholder' )
					.params( [ mw.language.convertNumber( languageMax ) ] )
					.text(),
				inputPosition: 'outline',
				tagLimit: languageMax,
				allowedValues: Object.keys( langCodeMap ),
				allowArbitrary: false,
				allowEditTags: false,
			} );

			const $warning = $( '<div>' )
				.addClass( 'warning' )
				.text( mw.message( 'welcomesurvey-question-languages-maximum' ).text() )
				.css( 'display', 'none' );

			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.welcomesurvey-languages .oo-ui-checkboxMultiselectInputWidget' )
				.css( 'display', 'none' )
				.after( widgetInstance.$element, $warning );

			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.welcomesurvey-languages' ).addClass( 'welcomesurvey-languages-loaded' );

			return widgetInstance;
		},
	};
}() );
