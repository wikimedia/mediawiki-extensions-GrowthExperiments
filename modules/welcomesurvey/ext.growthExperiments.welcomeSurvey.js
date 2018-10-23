// Most of the code in this file was copied from 'multiselect.js'
// in order to support 'allowArbitrary' and 'placeholder'

( function ( mw, $ ) {

	function convertCheckboxesWidgetToTags( fieldLayout ) {
		var checkboxesWidget, checkboxesOptions, menuTagOptions, menuTagWidget;

		checkboxesWidget = fieldLayout.fieldWidget;
		checkboxesOptions = checkboxesWidget.checkboxMultiselectWidget.getItems();
		menuTagOptions = checkboxesOptions.map( function ( option ) {
			return new OO.ui.MenuOptionWidget( {
				data: option.getData(),
				label: option.getLabel()
			} );
		} );
		menuTagWidget = new OO.ui.MenuTagMultiselectWidget( {
			$overlay: true,
			allowArbitrary: true,
			// todo: This should be configured in the php widget and propagated here
			placeholder: mw.message( 'welcomesurvey-tagmultiselect-placeholder' ).text(),
			menu: {
				items: menuTagOptions
			}
		} );
		menuTagWidget.setValue( checkboxesWidget.getValue() );

		// Data from CapsuleMultiselectWidget will not be submitted with the form, so keep the original
		// CheckboxMultiselectInputWidget up-to-date.
		menuTagWidget.on( 'change', function () {
			checkboxesWidget.setValue( menuTagWidget.getValue() );
		} );

		menuTagWidget.on( 'add', function ( tagItemWidget ) {
			var option,
				data = tagItemWidget.getData(),
				item = checkboxesWidget.checkboxMultiselectWidget.findItemFromData( data );
			if ( !item ) {
				option = new OO.ui.CheckboxMultioptionWidget( {
					data: data,
					selected: true,
					label: data
				} );
				option.checkbox = new OO.ui.CheckboxInputWidget( {
					name: checkboxesWidget.inputName,
					value: data
				} );
				option.$element.empty().prepend( option.checkbox.$element );
				checkboxesWidget.checkboxMultiselectWidget.addItems( option );
			}
		} );

		// Hide original widget and add new one in its place. This is a bit hacky, since the FieldLayout
		// still thinks it's connected to the old widget.
		checkboxesWidget.toggle( false );
		checkboxesWidget.$element.after( menuTagWidget.$element );
	}

	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		var $dropdowns = $root.find( '.oo-ui-fieldLayout.custom-dropdown' );
		if ( $dropdowns.length ) {
			$dropdowns.each( function () {
				var $el = $( this ),
					data, modules, extraModules;
				if ( $el.is( '[data-ooui]' ) ) {
					// Load 'oojs-ui-widgets' for CapsuleMultiselectWidget
					modules = [ 'mediawiki.htmlform.ooui', 'oojs-ui-widgets' ];
					data = $el.data( 'mw-modules' );
					if ( data ) {
						// We can trust this value, 'data-mw-*' attributes are banned from user content in Sanitizer
						extraModules = data.split( ',' );
						modules.push.apply( modules, extraModules );
					}
					mw.loader.using( modules, function () {
						convertCheckboxesWidgetToTags( OO.ui.FieldLayout.static.infuse( $el ) );
					} );
				}
			} );
		}
	} );

} )( mw, jQuery );
