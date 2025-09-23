/**
 * GroupWidget that groups SuggestionWidgets
 *
 * @param {Object} config
 * @param {boolean} [config.selectAll] If true, add a "select all" link
 * @param {string} [config.header] Header message to show above the group
 * @param {SuggestionWidget[]} [config.items] Items to add initially
 * @param {SuggestionWidget[]} [config.hiddenItems] Items initially hidden behind "show more"
 */
function SuggestionGroupWidget( config ) {
	config = config || {};
	// Parent constructor
	SuggestionGroupWidget.super.call( this, config );
	// Mixin constructor
	OO.ui.mixin.GroupWidget.call( this, config );

	this.aggregate( { toggleSuggestion: 'toggleSuggestion' } );
	this.connect( this, {
		toggleSuggestion: 'updateSelectAllButtonLabel',
		add: 'updateSelectAllButtonLabel',
		clear: 'updateSelectAllButtonLabel',
		remove: 'updateSelectAllButtonLabel',
	} );
	if ( config.items ) {
		this.addItems( config.items );
	}
	this.hiddenItems = config.hiddenItems || [];

	this.$header = $( [] );
	if ( config.header ) {
		this.$header = $( '<h4>' )
			.addClass( 'mw-ge-suggestionGroupWidget-headerRow-header' )
			.append( $( '<span>' ).text( config.header ) );
	}

	this.selectAllButton = null;
	if ( config.selectAll ) {
		this.selectAllButton = new OO.ui.ButtonWidget( {
			classes: [ 'mw-ge-suggestionGroupWidget-headerRow-select-all' ],
			label: '', // set by updateSelectAllButtonLabel()
			flags: [ 'progressive' ],
			framed: false,
		} );
		this.updateSelectAllButtonLabel();
		this.selectAllButton.connect( this, { click: 'onSelectAllButtonClick' } );
		this.$header.append( this.selectAllButton.$element );
	}

	this.showMoreButton = null;
	if ( this.hiddenItems.length > 0 ) {
		this.showMoreButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'growthexperiments-homepage-suggestededits-topics-more' ),
			flags: [ 'progressive' ],
			framed: false,
		} );
		this.showMoreButton.connect( this, { click: 'onShowMoreButtonClick' } );
	}

	this.$group
		.addClass( 'mw-ge-hide-outline' )
		.on( 'keydown', this.onKeydown.bind( this ) );

	this.$element
		.addClass( 'mw-ge-SuggestionGroupWidget' )
		.append(
			$( '<div>' ).addClass( 'mw-ge-suggestionGroupWidget-headerRow' ).append(
				this.$header,
				this.selectAllButton && this.selectAllButton.$element,
			),
			this.$group,
			this.showMoreButton && this.showMoreButton.$element,
		);
}

OO.inheritClass( SuggestionGroupWidget, OO.ui.Widget );
OO.mixinClass( SuggestionGroupWidget, OO.ui.mixin.GroupWidget );

/**
 * Remove class when tab key is pressed to ensure user sees focus outline.
 *
 * @param {Object} e
 */
SuggestionGroupWidget.prototype.onKeydown = function ( e ) {
	if ( e.key === 'Tab' ) {
		this.$element.removeClass( 'mw-ge-hide-outline' );
	}
};

/**
 * Handle clicks on the select all / unselect all button
 */
SuggestionGroupWidget.prototype.onSelectAllButtonClick = function () {
	const newState = !this.isEverythingSelected();
	this.getItems().forEach( ( suggestion ) => {
		suggestion.toggleSuggestion( newState );
	} );
	this.emit( newState ? 'selectAll' : 'removeAll' );
};

/**
 * Handle clicks on the "show more" button. This removes the button.
 */
SuggestionGroupWidget.prototype.onShowMoreButtonClick = function () {
	this.addItems( this.hiddenItems );
	this.hiddenItems = [];
	this.showMoreButton.$element.detach();
	this.emit( 'expand' );
};

/**
 * Update the label of the "select all" button to either "select all" or "remove all",
 * depending on whether all suggestions are selected or not
 */
SuggestionGroupWidget.prototype.updateSelectAllButtonLabel = function () {
	if ( this.selectAllButton ) {
		this.selectAllButton.setLabel( this.isEverythingSelected() ?
			mw.msg( 'growthexperiments-homepage-suggestededits-topics-unselectall' ) :
			mw.msg( 'growthexperiments-homepage-suggestededits-topics-selectall' ),
		);
	}
};

/**
 * Check whether all suggestions are selected.
 *
 * @return {boolean} All suggestions are selected
 */
SuggestionGroupWidget.prototype.isEverythingSelected = function () {
	return this.getItems().every( ( suggestion ) => suggestion.confirmed );
};

/**
 * Get the data of the selected suggestions.
 *
 * @return {Object[]} Data object for each suggestion that is selected
 */
SuggestionGroupWidget.prototype.getSelectedSuggestions = function () {
	return this.getItems()
		.filter( ( suggestion ) => suggestion.confirmed )
		.map( ( suggestion ) => suggestion.suggestionData );
};

module.exports = SuggestionGroupWidget;
