var SuggestionWidget = require( './ext.growthExperiments.Homepage.SuggestionWidget.js' ),
	SuggestionGroupWidget = require( './ext.growthExperiments.Homepage.SuggestionGroupWidget.js' ),
	topicData = require( './Topics.json' );

/**
 * Widget that lets the user select topics using SuggestionWidgets.
 * Displays a limited number of topics initially, with a "show more" button to display the rest.
 *
 * @param {Object} config
 * @cfg {number} [initialLimit=12] Number of topics to display initially; use Infinity to disable
 * @cfg {string[]} [selectedTopics=[]] IDs of initially selected topics
 */
function TopicSelectionWidget( config ) {
	config = $.extend( {
		initialLimit: 12,
		selectedTopics: []
	}, config );

	// Parent constructor
	TopicSelectionWidget.parent.call( this, config );

	this.initialLimit = config.initialLimit;
	this.suggestions = Object.keys( topicData ).map( function ( key ) {
		var topic = topicData[ key ];
		return new SuggestionWidget( { suggestionData: {
			id: topic.id,
			text: topic.name,
			confirmed: config.selectedTopics.indexOf( topic.id ) !== -1
		} } ).on( 'toggleSuggestion', function () {
			this.emit( 'toggleSelection' );
		}.bind( this ) );
	}.bind( this ) );

	if ( config.initialLimit >= 0 && isFinite( config.initialLimit ) ) {
		this.displayedSuggestions = this.suggestions.slice( 0, config.initialLimit );
		this.hiddenSuggestions = this.suggestions.slice( config.initialLimit );
	} else {
		this.displayedSuggestions = this.suggestions;
		this.hiddenSuggestions = [];
	}

	this.suggestionGroup = new SuggestionGroupWidget();
	this.suggestionGroup.addItems( this.displayedSuggestions );

	this.$element
		.addClass( 'mw-ge-TopicSelectionWidget' )
		.append( this.suggestionGroup.$element );

	if ( this.hiddenSuggestions.length > 0 ) {
		this.showMoreButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'growthexperiments-homepage-suggestededits-topics-more' ),
			flags: [ 'progressive' ],
			framed: false
		} );
		this.showMoreButton.connect( this, { click: 'onShowMoreButtonClick' } );
		this.suggestionGroup.$element.append( this.showMoreButton.$element );
	}
}

OO.inheritClass( TopicSelectionWidget, OO.ui.Widget );

/**
 * Show all suggestion items and detach the show more button.
 */
TopicSelectionWidget.prototype.showAllItems = function () {
	this.suggestionGroup.addItems( this.hiddenSuggestions );
	this.displayedSuggestions = this.suggestions;
	this.hiddenSuggestions = [];
	this.showMoreButton.$element.detach();
};

/**
 * Callback when user clicks the "Show more" button.
 */
TopicSelectionWidget.prototype.onShowMoreButtonClick = function () {
	this.showAllItems();
	this.emit( 'expand' );
};

/**
 * Get the IDs of all selected topics, both those that are visible and selected, and those that are
 * hidden and were preselected.
 * @return {string[]} IDs of selected topics
 */
TopicSelectionWidget.prototype.getSelectedTopics = function () {
	// Get the state of all suggestion widgets, even the hidden ones!
	return this.suggestions
		.filter( function ( suggestion ) {
			return suggestion.confirmed;
		} )
		.map( function ( suggestion ) {
			return suggestion.suggestionData.id;
		} );
};

/**
 * Get the IDs of all selected topics which were visible without clicking on the "show more" link.
 * @return {string[]} IDs of selected topics
 */
TopicSelectionWidget.prototype.getAboveFoldSelectedTopics = function () {
	return this.suggestions
		.filter( function ( suggestion, i ) {
			return suggestion.confirmed && i < this.initialLimit;
		}.bind( this ) )
		.map( function ( suggestion ) {
			return suggestion.suggestionData.id;
		} );
};

module.exports = TopicSelectionWidget;
