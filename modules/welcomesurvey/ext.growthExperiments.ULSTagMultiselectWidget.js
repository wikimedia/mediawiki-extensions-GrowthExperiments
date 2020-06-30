( function ( OO ) {
	'use strict';

	/**
	 * A TagMultiselectWidget with the input widget overridden to launch
	 * the jQuery ULS widget on desktop, and the language picker overlay on
	 * mobile.
	 *
	 * @param {Object} config
	 * @param {Object} config.langCodeMap A mapping of language codes to language names
	 * @constructor
	 */
	var ULSTagMultiselectWidget = function UlsTagMultiselectWidget( config ) {
		var shouldUseLanguageOverlay = OO.ui.isMobile() &&
			mw.mobileFrontend.require( 'mobile.startup' ).languageInfoOverlay;
		this.langCodeMap = config.langCodeMap;
		ULSTagMultiselectWidget.super.call( this, config );
		this.$element.on( 'click', function ( e ) {
			// Intercept clicks to the built-in input widget which we don't
			// care about, and redirect them to ULS.
			e.stopPropagation();
			if ( shouldUseLanguageOverlay ) {
				this.emit( 'inputFocus' );
			} else {
				this.initializeUls();
				this.$uls.trigger( 'click' );
			}
		}.bind( this ) );
		// This is done here rather than when instantiating the widget so that
		// we can get the display name for the current language, rather than the
		// language code.
		this.addLanguageByCode( mw.config.get( 'wgUserLanguage' ) );
	};

	OO.inheritClass( ULSTagMultiselectWidget, OO.ui.TagMultiselectWidget );

	/**
	 * Set up the ULS trigger if not already defined.
	 *
	 * This needs to happen after the TagMultiSelectWidget is rendered so that
	 * we can get meaningful information about the offset.
	 */
	ULSTagMultiselectWidget.prototype.initializeUls = function () {
		var $inputWidget, offset;

		if ( this.$uls ) {
			return;
		}
		// eslint-disable-next-line no-jquery/no-global-selector
		this.$uls = $( '.welcomesurvey-languages-uls' );

		$inputWidget = this.$uls.find( '.oo-ui-tagMultiselectWidget-input' );
		offset = $inputWidget.offset();

		this.$uls.uls( {
			ulsPurpose: 'welcomesurvey-languages-picker',
			menuWidth: 'medium',
			left: offset.left,
			top: offset.top,
			onVisible: function () {
				// Hack to fit the ULS with the width of the input widget.
				this.$menu.width( $inputWidget[ 0 ].clientWidth - 2 );
			},
			onSelect: function ( lang ) {
				this.addLanguageByCode( lang );
			}.bind( this )
		} );
	};

	/**
	 * Place the user selected languages into a hidden form field.
	 *
	 * @param {Object[]} items
	 */
	ULSTagMultiselectWidget.prototype.onChangeTags = function ( items ) {
		var selectedLangCodes;
		ULSTagMultiselectWidget.super.prototype.onChangeTags.apply( this, arguments );
		selectedLangCodes = items.map( function ( item ) {
			return item.getData();
		} );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'input[name="wplanguages[]"]' ).each( function ( index, checkbox ) {
			$( checkbox ).prop( 'checked', selectedLangCodes.indexOf( checkbox.value ) !== -1 );
		} );
	};

	/**
	 * Add the user selected language to the TagMultiselectWidget display,
	 * using the ULS language display name, e.g. "en" for the data and "English"
	 * for its display in the widget.
	 *
	 * @param {string} langCode
	 */
	ULSTagMultiselectWidget.prototype.addLanguageByCode = function ( langCode ) {
		this.addTag( langCode, this.langCodeMap[ langCode ] );
	};

	module.exports = ULSTagMultiselectWidget;

}( OO ) );
