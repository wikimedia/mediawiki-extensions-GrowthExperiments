/**
 * Add a text input field to the specified option widget
 *
 * @param {OO.ui.CheckboxMultioptionWidget} optionWidget
 * @param {Object} [config]
 * @param {string} [config.placeholder]
 * @constructor
 */
function OptionWithTextInputWidget( optionWidget, config ) {
	config = config || {};
	/**
	 * @property {OO.ui.CheckboxMultioptionWidget} optionWidget
	 */
	this.optionWidget = optionWidget;
	this.optionWidget.$element.addClass( 'mw-ge-optionWithTextInputWidget' );
	this.setupTextInput( config );
}

/**
 * Set up TextInputWidget and add it to the option widget
 *
 * @param {Object} [textInputConfig]
 * @param {string} [textInputConfig.placeholder] Placeholder text for the input
 * @param {number} [textInputConfig.maxLength] Maximum number of characters allowed in the input
 */
OptionWithTextInputWidget.prototype.setupTextInput = function ( textInputConfig ) {
	var widgetConfig = {
		disabled: !this.optionWidget.isSelected(),
		placeholder: textInputConfig.placeholder,
		classes: [ 'mw-ge-optionWithTextInputWidget-textInput' ]
	};
	if ( textInputConfig.maxLength && typeof textInputConfig.maxLength === 'number' ) {
		widgetConfig.maxLength = textInputConfig.maxLength;
	}
	this.textInputWidget = new OO.ui.TextInputWidget( widgetConfig );
	this.optionWidget.$label.append( this.textInputWidget.$element );
	this.optionWidget.on( 'change', function ( isSelected ) {
		this.textInputWidget.setDisabled( !isSelected );
	}.bind( this ) );
};

/**
 * Get the option widget with added text input
 *
 * @return {OO.ui.CheckboxMultioptionWidget|OO.ui.RadioOptionWidget}
 */
OptionWithTextInputWidget.prototype.getOptionWidget = function () {
	return this.optionWidget;
};

/**
 * Get the value of the text input field; return an empty string if the option is not selected
 *
 * @return {string}
 */
OptionWithTextInputWidget.prototype.getTextInputValue = function () {
	return this.optionWidget.isSelected() ? this.textInputWidget.getValue() : '';
};

/**
 * Set the value of the text input field
 *
 * @param {string} value
 */
OptionWithTextInputWidget.prototype.setTextInputValue = function ( value ) {
	this.textInputWidget.setValue( value );
};

module.exports = OptionWithTextInputWidget;
