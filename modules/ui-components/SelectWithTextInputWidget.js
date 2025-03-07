/**
 * Wrapper around OOUI's select widgets for converting a list of options into a widget
 * and for normalizing methods for single & multiple selections.
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
	const OptionWidgetClass = this.isMultiSelect ?
		OO.ui.CheckboxMultioptionWidget :
		OO.ui.RadioOptionWidget;
	// eslint-disable-next-line mediawiki/class-doc
	const optionWidget = new OptionWidgetClass( {
		data: itemData.data,
		label: itemData.label,
		classes: itemData.classes || []
	} );
	return optionWidget;
};

/**
 * Construct CheckboxMultiselectWidget if isMultiSelect is true,
 * otherwise construct RadioSelectWidget
 *
 * @return {OO.ui.CheckboxMultiselectWidget|OO.ui.RadioSelectWidget}
 */
SelectWithTextInputWidget.prototype.constructWidget = function () {
	const SelectWidgetClass = this.isMultiSelect ?
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
	let selectedItems;
	if ( this.isMultiSelect ) {
		selectedItems = this.widget.findSelectedItems();
	} else {
		const selectedItem = this.widget.findSelectedItem();
		selectedItems = selectedItem ? [ selectedItem ] : [];
	}
	return selectedItems.map( ( item ) => item.getData() );
};

module.exports = SelectWithTextInputWidget;
