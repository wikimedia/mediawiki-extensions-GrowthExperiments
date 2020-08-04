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

			// Don't open ULS or the language overlay if we're at the tag limit
			if ( !this.isUnderLimit() ) {
				return;
			}

			if ( shouldUseLanguageOverlay ) {
				this.emit( 'inputFocus' );
			} else {
				this.initializeUls();
				this.$uls.trigger( 'click' );
			}
		}.bind( this ) );
		// This is done here rather than when instantiating the widget so that
		// we can get the display name for the content language, rather than the
		// language code.
		this.addLanguageByCode( mw.config.get( 'wgContentLanguage' ), true );
	};

	OO.inheritClass( ULSTagMultiselectWidget, OO.ui.TagMultiselectWidget );

	/**
	 * Set up the ULS trigger if not already defined.
	 *
	 * This needs to happen after the TagMultiSelectWidget is rendered so that
	 * we can get meaningful information about the offset.
	 */
	ULSTagMultiselectWidget.prototype.initializeUls = function () {
		var $inputWidget, offset,
			widget = this;

		if ( this.$uls ) {
			return;
		}
		this.$uls = this.$element.closest( '.oo-ui-fieldLayout' );

		$inputWidget = this.input.$element;
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
				widget.addLanguageByCode( lang );
				this.$languageFilter.languagefilter( 'clear' );
			}
		} );
	};

	/**
	 * Place the user selected languages into a hidden form field.
	 *
	 * @param {Object[]} items
	 */
	ULSTagMultiselectWidget.prototype.onChangeTags = function ( items ) {
		var selectedLangCodes;
		// Parent method
		ULSTagMultiselectWidget.super.prototype.onChangeTags.apply( this, arguments );

		// Update the hidden checkboxes in the form
		selectedLangCodes = items.map( function ( item ) {
			return item.getData();
		} );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'input[name="wplanguages[]"]' ).each( function ( index, checkbox ) {
			$( checkbox ).prop( 'checked', selectedLangCodes.indexOf( checkbox.value ) !== -1 );
		} );

		// Show a different placeholder if we're at the limit
		if ( !this.isUnderLimit() ) {
			this.input.$input.attr( 'placeholder',
				mw.message( 'welcomesurvey-question-languages-placeholder-maximum' ).text()
			);
		}
		// else: the parent method will restore the regular placeholder
	};

	/**
	 * Add the user selected language to the TagMultiselectWidget display,
	 * using the ULS language display name, e.g. "en" for the data and "English"
	 * for its display in the widget.
	 *
	 * @param {string} langCode
	 * @param {boolean} [fixed] If true, the user won't be allowed to remove this language
	 */
	ULSTagMultiselectWidget.prototype.addLanguageByCode = function ( langCode, fixed ) {
		this.addTag( langCode, this.langCodeMap[ langCode ] );
		if ( fixed ) {
			this.findItemFromData( langCode ).setFixed( true );
		}
	};

	module.exports = ULSTagMultiselectWidget;

}( OO ) );
