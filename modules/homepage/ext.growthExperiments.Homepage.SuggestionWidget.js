// TODO rewrite this using SelectWidget/OptionWidget some time in the future

/**
 * A single suggested tag for an image.
 *
 * Copied from mediawiki/extensions/MachineVision/resources/widgets/SuggestionWidget.js
 * and adapted for use outside the MachineVision extension
 *
 * @param {Object} config
 * @cfg {Object} suggestionData
 */
function SuggestionWidget( config ) {
	this.suggestionData = config.suggestionData;
	this.confirmed = this.suggestionData.confirmed;

	SuggestionWidget.parent.call( this, $.extend( {}, config ) );

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

	this.checkIcon = new OO.ui.IconWidget( {
		icon: 'check'
	} );

	this.$suggestion = $( '<div>' )
		.addClass( 'wbmad-suggestion' )
		.append( this.suggestionLabel.$element, this.checkIcon.$element );

	this.$element
		.addClass( 'wbmad-suggestion-wrapper' )
		.append( this.$suggestion )
		.on( {
			click: this.toggleSuggestion.bind( this ),
			keydown: this.onKeydown.bind( this )
		} )
		// Ensure element is focusable
		.attr( 'tabindex', 0 );

	this.update();
}

OO.inheritClass( SuggestionWidget, OO.ui.Widget );

SuggestionWidget.prototype.update = function () {
	this.$suggestion
		.toggleClass( 'wbmad-suggestion--confirmed', this.confirmed )
		.toggleClass( 'wbmad-suggestion--unconfirmed', !this.confirmed );
	this.checkIcon.toggle( this.confirmed );
};

/**
 * Handle click/enter on suggestion widget.
 *
 * Store confirmed status in local "state", tell parent widget about this
 * change, then re-render the suggestion widget.
 */
SuggestionWidget.prototype.toggleSuggestion = function () {
	this.confirmed = !this.confirmed;
	this.emit( 'toggleSuggestion', this.confirmed );

	this.update();
};

/**
 * Toggle the suggestion on enter keypress.
 * @param {Object} e
 */
SuggestionWidget.prototype.onKeydown = function ( e ) {
	if ( e.key === 'Enter' ) {
		this.toggleSuggestion();
	}
};

module.exports = SuggestionWidget;
