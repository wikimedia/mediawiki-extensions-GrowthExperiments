/**
 * GroupWidget that groups SuggestionWidgets
 * @param {Object} config
 */
function SuggestionGroupWidget( config ) {
	// Parent constructor
	SuggestionGroupWidget.parent.call( this, config );
	// Mixin constructor
	OO.ui.mixin.GroupWidget.call( this, $.extend( { $group: this.$element }, config ) );

	this.$element
		.addClass( 'mw-ge-SuggestionGroupWidget wbmad-hide-outline' )
		.on( 'keydown', this.onKeydown.bind( this ) );
}

OO.inheritClass( SuggestionGroupWidget, OO.ui.Widget );
OO.mixinClass( SuggestionGroupWidget, OO.ui.mixin.GroupWidget );

/**
 * Remove class when tab key is pressed to ensure user sees focus outline.
 * @param {Object} e
 */
SuggestionGroupWidget.prototype.onKeydown = function ( e ) {
	if ( e.key === 'Tab' ) {
		this.$element.removeClass( 'wbmad-hide-outline' );
	}
};

SuggestionGroupWidget.prototype.getSelectedSuggestions = function () {
	this.getItems()
		.filter( function ( suggestion ) {
			return suggestion.confirmed;
		} )
		.map( function ( suggestion ) {
			return suggestion.suggestionData;
		} );
};

module.exports = SuggestionGroupWidget;
