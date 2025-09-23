// TODO rewrite this using SelectWidget/OptionWidget some time in the future

/**
 * A single suggested tag for an image.
 *
 * Copied from mediawiki/extensions/MachineVision/resources/widgets/SuggestionWidget.js
 * and adapted for use outside the MachineVision extension
 *
 * @param {Object} config
 * @param {Object} config.suggestionData
 */
function SuggestionWidget( config ) {
	this.suggestionData = config.suggestionData;
	this.confirmed = this.suggestionData.confirmed;

	SuggestionWidget.super.call( this, Object.assign( {}, config ) );

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text,
	} );

	this.checkIcon = new OO.ui.IconWidget( {
		icon: 'check',
	} );

	this.$suggestion = $( '<div>' )
		.addClass( 'mw-ge-suggestion' )
		.append( this.suggestionLabel.$element, this.checkIcon.$element );

	this.$element
		.addClass( 'mw-ge-suggestion-wrapper' )
		.append( this.$suggestion )
		.on( {
			click: this.onClick.bind( this ),
			keydown: this.onKeydown.bind( this ),
		} )
		// Ensure element is focusable
		.attr( 'tabindex', 0 );

	this.update();
}

OO.inheritClass( SuggestionWidget, OO.ui.Widget );

SuggestionWidget.prototype.update = function () {
	this.$suggestion
		.toggleClass( 'mw-ge-suggestion--confirmed', this.confirmed )
		.toggleClass( 'mw-ge-suggestion--unconfirmed', !this.confirmed );
	this.checkIcon.toggle( this.confirmed );
};

/**
 * Handle click/enter on suggestion widget.
 *
 * Store confirmed status in local "state", tell parent widget about this
 * change, then re-render the suggestion widget.
 *
 * If the newState parameter is omitted, the state is toggled (set to the opposite of the
 * current state).
 *
 * @param {boolean} [newState] True to set the widget to selected, false to set to unselected
 */
SuggestionWidget.prototype.toggleSuggestion = function ( newState ) {
	this.confirmed = newState === undefined ? !this.confirmed : !!newState;
	this.emit( 'toggleSuggestion', this.confirmed );

	this.update();
};

SuggestionWidget.prototype.onClick = function () {
	this.toggleSuggestion();
};

/**
 * Toggle the suggestion on enter keypress.
 *
 * @param {Object} e
 */
SuggestionWidget.prototype.onKeydown = function ( e ) {
	if ( e.key === 'Enter' ) {
		this.toggleSuggestion();
	}
};

module.exports = SuggestionWidget;
