var OptionWithTextInputWidget = require( './OptionWithTextInputWidget.js' );

/**
 * Wrapper around OOUI's select widgets for converting a list of options into a widget
 * and for normalizing methods for single & multiple selections. Options can be accompanied by
 * a text input.
 *
 * @class mw.libs.ge.SelectWithTextInputWidget
 * @constructor
 *
 * @param {Object} config
 * @param {Object[]} config.options Options to show
 * @param {boolean} [config.isMultiSelect] Whether multiple options can be selected
 */
function SelectWithTextInputWidget( config ) {
	this.options = config.options;
	this.isMultiSelect = typeof config.isMultiSelect === 'boolean' ? config.isMultiSelect : false;
	// Mapping between option data and OptionWithTextInputWidget; used to retrieve free text data
	// associated with the option
	this.optionWithTextInputWidgets = {};
	this.widget = this.constructWidget();
}

/**
 * Construct radio option item if isMultiSelect is true,
 * otherwise construct checkbox item
 *
 * @param {Object} itemData
 * @return {OO.ui.CheckboxMultioptionWidget|OO.ui.RadioOptionWidget|undefined}
 */
SelectWithTextInputWidget.prototype.constructItem = function ( itemData ) {
	if ( !itemData.data && !itemData.label ) {
		return;
	}
	var OptionWidgetClass = this.isMultiSelect ?
		OO.ui.CheckboxMultioptionWidget :
		OO.ui.RadioOptionWidget;
	// eslint-disable-next-line mediawiki/class-doc
	var optionWidget = new OptionWidgetClass( {
		data: itemData.data,
		label: itemData.label,
		classes: itemData.classes || []
	} );

	// Additional changes are needed for RadioOptionWidget to work (change event is not emitted when
	// the selection changes; DOM structure needs to be updated so that the TextInput can be selected
	// even within a radio button group)
	if ( itemData.hasTextInput && this.isMultiSelect ) {
		this.optionWithTextInputWidgets[ itemData.data ] = new OptionWithTextInputWidget(
			optionWidget,
			{
				placeholder: itemData.textInputPlaceholder,
				maxLength: itemData.textInputMaxLength
			}
		);
		return this.optionWithTextInputWidgets[ itemData.data ].getOptionWidget();
	}
	return optionWidget;
};

/**
 * Construct CheckboxMultiselectWidget if isMultiSelect is true,
 * otherwise construct RadioSelectWidget
 *
 * @return {OO.ui.CheckboxMultiselectWidget|OO.ui.RadioSelectWidget}
 */
SelectWithTextInputWidget.prototype.constructWidget = function () {
	var SelectWidgetClass = this.isMultiSelect ?
		OO.ui.CheckboxMultiselectWidget :
		OO.ui.RadioSelectWidget;

	return new SelectWidgetClass( {
		items: this.options.map( ( itemData ) => this.constructItem( itemData ) )
	} );
};

/**
 * Get the selection widget element
 *
 * @return {jQuery}
 */
SelectWithTextInputWidget.prototype.getElement = function () {
	return this.widget.$element;
};

/**
 * Update selected state of the options based on the specified data
 *
 * @param {*|*[]} data
 */
SelectWithTextInputWidget.prototype.updateSelection = function ( data ) {
	if ( this.isMultiSelect ) {
		this.widget.selectItemsByData( Array.isArray( data ) ? data : [ data ] );
	} else {
		this.widget.selectItemByData( Array.isArray( data ) ? data[ 0 ] : data );
	}
};

/**
 * Find selected item(s) and return the data as an array
 *
 * @return {*[]}
 */
SelectWithTextInputWidget.prototype.findSelection = function () {
	var selectedItems;
	if ( this.isMultiSelect ) {
		selectedItems = this.widget.findSelectedItems();
	} else {
		var selectedItem = this.widget.findSelectedItem();
		selectedItems = selectedItem ? [ selectedItem ] : [];
	}
	return selectedItems.map( ( item ) => item.getData() );
};

/**
 * Get the text input value associated with the specified data
 *
 * @param {string} data Option data
 * @return {string}
 */
SelectWithTextInputWidget.prototype.getTextInputValueForData = function ( data ) {
	var widgetForData = this.optionWithTextInputWidgets[ data ];
	if ( widgetForData instanceof OptionWithTextInputWidget ) {
		return widgetForData.getTextInputValue();
	}
	return '';
};

/**
 * Set the text input value associated with the specified data
 *
 * @param {string} data Option data
 * @param {string} value Text input value
 */
SelectWithTextInputWidget.prototype.updateTextInputValueForData = function ( data, value ) {
	var widgetForData = this.optionWithTextInputWidgets[ data ];
	if ( widgetForData instanceof OptionWithTextInputWidget ) {
		widgetForData.setTextInputValue( value );
	}
};

module.exports = SelectWithTextInputWidget;
