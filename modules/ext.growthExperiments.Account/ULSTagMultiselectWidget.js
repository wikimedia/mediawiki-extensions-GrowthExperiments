( function ( OO ) {
	'use strict';

	/**
	 * A TagMultiselectWidget with the input widget overridden to launch
	 * the jQuery ULS widget on desktop, and the language picker overlay on
	 * mobile.
	 *
	 * @param {Object} config
	 * @constructor
	 */
	const ULSTagMultiselectWidget = function UlsTagMultiselectWidget( config ) {
		const shouldUseLanguageOverlay = OO.ui.isMobile() &&
			mw.loader.getState( 'mobile.startup' ) === 'loaded';
		ULSTagMultiselectWidget.super.call( this, config );
		this.$element.on( 'click', ( e ) => {
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
		} );
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
		const widget = this;

		if ( this.$uls ) {
			return;
		}
		this.$uls = this.$element.closest( '.oo-ui-fieldLayout' );

		const $inputWidget = this.input.$element;
		const offset = $inputWidget.offset();

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
			},
		} );
	};

	/**
	 * Place the user selected languages into a hidden form field.
	 *
	 * @param {Object[]} items
	 */
	ULSTagMultiselectWidget.prototype.onChangeTags = function ( items ) {
		// Parent method
		ULSTagMultiselectWidget.super.prototype.onChangeTags.apply( this, arguments );

		// Update the hidden checkboxes in the form
		const selectedLangCodes = items.map( ( item ) => item.getData() );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'input[name="wplanguages[]"]' ).each( ( index, checkbox ) => {
			$( checkbox ).prop( 'checked', selectedLangCodes.includes( checkbox.value ) );
		} );

		// Show or hide the error message about selecting too many languages
		// TODO this and the above should use events instead
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.welcomesurvey-languages .warning' ).toggle( !this.isUnderLimit() );

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
		// the allowedValues list we pass to the constructor doesn't include language aliases,
		// so resolve them here before passing to addTag
		langCode = $.uls.data.isRedirect( langCode ) || langCode;
		this.addTag( langCode, $.uls.data.getAutonym( langCode ) );
		if ( fixed ) {
			this.findItemFromData( langCode ).setFixed( true );
		}
	};

	module.exports = ULSTagMultiselectWidget;

}( OO ) );
