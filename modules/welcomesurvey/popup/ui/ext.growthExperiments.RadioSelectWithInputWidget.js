( function () {

	/**
	 * Same as OO.ui.RadioSelectWidget but with an 'other' option
	 * to enter free text.
	 *
	 * @param {Object} config
	 * @cfg {string} otherOptionText Text of the 'other' option
	 * @cfg {Object} select Configuration for the OO.ui.RadioSelectWidget
	 * @cfg {Object} input Configuration for the OO.ui.TextInputWidget
	 * @constructor
	 */
	function RadioSelectWithInputWidget( config ) {
		RadioSelectWithInputWidget.parent.call( this, config );

		config = $.extend( true, {
			select: {
				items: []
			}
		}, config );
		config.select.items.push(
			new OO.ui.RadioOptionWidget( {
				data: 'other',
				label: config.otherOptionText
			} )
		);
		this.radioSelect = new OO.ui.RadioSelectWidget( config.select );
		this.input = new OO.ui.TextInputWidget( config.input );

		this.radioSelect.connect( this, { choose: 'onRadioSelectChoose' } );

		this.$element.append(
			this.radioSelect.$element,
			this.input.$element
		);
		this.onRadioSelectChoose();
	}
	OO.inheritClass( RadioSelectWithInputWidget, OO.ui.Widget );

	/**
	 * Called when an option is chosen by the user.
	 * Make the text input visible if the chosen option is 'other'.
	 */
	RadioSelectWithInputWidget.prototype.onRadioSelectChoose = function () {
		var item = this.radioSelect.findSelectedItem();
		if ( item && item.getData() === 'other' ) {
			this.input.toggle( true );
			setTimeout( this.input.focus.bind( this.input ) );
		} else {
			this.input.toggle( false );
		}
	};

	/**
	 * Return the selected option's data, or the text if
	 * 'other' is selected.
	 *
	 * @return {string|null}
	 */
	RadioSelectWithInputWidget.prototype.getValue = function () {
		var item = this.radioSelect.findSelectedItem();

		if ( !item ) {
			return null;
		}
		return item.getData() === 'other' ?
			this.input.getValue() :
			item.getData();
	};

	OO.setProp(
		mw, 'libs', 'ge', 'WelcomeSurvey', 'RadioSelectWithInputWidget',
		RadioSelectWithInputWidget
	);

}() );
