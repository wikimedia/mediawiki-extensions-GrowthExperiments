/**
 * Wrapper around OOUI's select widgets for converting a list of rejection reasons into a widget
 * and for normalizing methods for single & multiple selections
 *
 * @class mw.libs.ge.RejectionReasonSelect
 * @constructor
 *
 * @param {Object} config
 * @param {Object[]} config.options Options to show
 * @param {boolean} [config.isMultiSelect] Whether multiple options can be selected
 */
function RejectionReasonSelect( config ) {
	this.options = config.options;
	this.isMultiSelect = typeof config.isMultiSelect === 'boolean' ? config.isMultiSelect : false;
	this.widget = this.constructWidget();
}

/**
 * Construct radio option item if isMultiSelect is true,
 * otherwise construct checkbox item
 *
 * @param {Object} itemData
 * @return {OO.ui.CheckboxMultioptionWidget|OO.ui.RadioOptionWidget}
 */
RejectionReasonSelect.prototype.constructItem = function ( itemData ) {
	if ( !itemData.data && !itemData.label ) {
		return;
	}
	var OptionWidgetClass = this.isMultiSelect ?
		OO.ui.CheckboxMultioptionWidget :
		OO.ui.RadioOptionWidget;
	// eslint-disable-next-line mediawiki/class-doc
	return new OptionWidgetClass( {
		data: itemData.data,
		label: itemData.label,
		classes: itemData.classes || []
	} );
};

/**
 * Construct CheckboxMultiselectWidget if isMultiSelect is true,
 * otherwise construct RadioSelectWidget
 *
 * @return {OO.ui.CheckboxMultiselectWidget|OO.ui.RadioSelectWidget}
 */
RejectionReasonSelect.prototype.constructWidget = function () {
	var SelectWidgetClass = this.isMultiSelect ?
		OO.ui.CheckboxMultiselectWidget :
		OO.ui.RadioSelectWidget;
	return new SelectWidgetClass( {
		items: this.options.map( function ( itemData ) {
			return this.constructItem( itemData );
		}.bind( this ) )
	} );
};

/**
 * Get the selection widget element
 *
 * @return {jQuery}
 */
RejectionReasonSelect.prototype.getElement = function () {
	return this.widget.$element;
};

/**
 * Update selected state of the options based on the specified data
 *
 * @param {*|*[]} data
 */
RejectionReasonSelect.prototype.updateSelection = function ( data ) {
	if ( this.isMultiSelect ) {
		this.widget.selectItemsByData( Array.isArray( data ) ? data : [ data ] );
	} else {
		this.widget.selectItemByData( data );
	}
};

/**
 * Find selected item(s) and return the data as an array
 *
 * @return {*[]}
 */
RejectionReasonSelect.prototype.findSelection = function () {
	var selectedItems;
	if ( this.isMultiSelect ) {
		selectedItems = this.widget.findSelectedItems();
	} else {
		var selectedItem = this.widget.findSelectedItem();
		selectedItems = selectedItem ? [ selectedItem ] : [];
	}
	return selectedItems.map( function ( item ) {
		return item.getData();
	} );
};

module.exports = RejectionReasonSelect;
