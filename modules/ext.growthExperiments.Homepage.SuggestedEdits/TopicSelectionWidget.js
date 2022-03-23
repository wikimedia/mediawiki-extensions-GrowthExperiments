var SuggestionWidget = require( './SuggestionWidget.js' ),
	SuggestionGroupWidget = require( './SuggestionGroupWidget.js' ),
	MatchModeSelectWidget = require( './MatchModeSelectWidget.js' ),
	topicData = require( './Topics.js' ),
	TopicFilters = require( './TopicFilters.js' ),
	TOPIC_MATCH_MODES = require( './constants.js' ).TOPIC_MATCH_MODES,
	TOPIC_MATCH_MODE_OPTIONS = [
		{
			data: TOPIC_MATCH_MODES.OR,
			label: mw.message( 'growthexperiments-homepage-suggestededits-topics-match-mode-any' ).text()
		},
		{
			data: TOPIC_MATCH_MODES.AND,
			label: mw.message( 'growthexperiments-homepage-suggestededits-topics-match-mode-all' ).text()
		}
	],
	groupedTopics = ( function () {
		var key, topic, grouped = {};
		for ( key in topicData ) {
			topic = topicData[ key ];
			if ( grouped[ topic.groupId ] === undefined ) {
				grouped[ topic.groupId ] = {
					id: topic.groupId,
					name: topic.groupName,
					topics: []
				};
			}
			grouped[ topic.groupId ].topics.push( topic );
		}
		return grouped;
	}() );

/**
 * Widget that lets the user select topics using SuggestionWidgets.
 *
 * If there are no topic groups, this displays a limited number of topics initially,
 * with a "show more" button to display the rest. This is controlled by config.initialLimit.
 *
 * If there are topic groups, separate SuggestionGroupWidgets are used for each groups, with headers
 * and "select/unselect all" buttons for each group. No "show more" buttons are displayed in this
 * case, and config.initialLimit is ignored.
 *
 * @param {Object} config
 * @cfg {number} [initialLimit=12] Number of topics to display initially; use Infinity to disable
 * @cfg {mw.libs.ge.TopicFilters} [filters=new TopicFilters()] Initially selected topic filters
 */
function TopicSelectionWidget( config ) {
	var key, group, groupWidget, suggestionWidgets, displayedSuggestionWidgets,
		hiddenSuggestionWidgets, anyHiddenSelected;
	config = $.extend( {
		initialLimit: 12,
		filters: new TopicFilters()
	}, config );

	// Parent constructor
	TopicSelectionWidget.parent.call( this, config );

	/* eslint-disable no-underscore-dangle */
	if ( topicData._error ) {
		// Handle errors from configuration loader early and return.
		mw.log.error( 'Unable to load topic data for suggested edits: ' + topicData._error );
		mw.errorLogger.logError( new Error( 'Unable to load topic data for suggested edits: ' +
			topicData._error ), 'error.growthexperiments' );
		this.suggestions = [];
		return;
	}
	/* eslint-enable no-underscore-dangle */

	if ( config.isMatchModeEnabled ) {
		this.matchModeSelector = new MatchModeSelectWidget( {
			classes: [ 'mw-ge-TopicSelectionWidget__match-mode' ],
			options: TOPIC_MATCH_MODE_OPTIONS,
			initialValue: config.filters.getTopicsMatchMode() || TOPIC_MATCH_MODES.OR
		} );
		this.matchModeSelector.connect( this, {
			toggleSelection: [ 'emit', 'toggleMatchMode' ],
			onMatchModeClick: [ 'emit', 'onMatchModeClick' ]
		} );
		this.$element.append( this.matchModeSelector.$element );
	}

	this.suggestions = [];
	this.suggestionGroupWidgets = [];
	for ( key in groupedTopics ) {
		group = groupedTopics[ key ];
		suggestionWidgets = group.topics.map( function ( topic ) {
			return new SuggestionWidget( { suggestionData: {
				id: topic.id,
				text: topic.name,
				confirmed: config.filters.getTopics().indexOf( topic.id ) !== -1
			} } );
		} );
		displayedSuggestionWidgets = suggestionWidgets;
		hiddenSuggestionWidgets = [];

		// If there are no topic groups, all topics are in one group whose ID is null
		if ( group.id === null ) {
			if (
				config.initialLimit >= 0 &&
				isFinite( config.initialLimit )
			) {
				displayedSuggestionWidgets = suggestionWidgets.slice( 0, config.initialLimit );
				hiddenSuggestionWidgets = suggestionWidgets.slice( config.initialLimit );
			}
			// If any of the suggestions we want to hide is selected, don't hide anything
			anyHiddenSelected = hiddenSuggestionWidgets.some( function ( suggestion ) {
				return suggestion.confirmed;
			} );
			if ( anyHiddenSelected ) {
				displayedSuggestionWidgets = suggestionWidgets;
				hiddenSuggestionWidgets = [];
			}
		}

		groupWidget = new SuggestionGroupWidget( {
			items: displayedSuggestionWidgets,
			hiddenItems: hiddenSuggestionWidgets,
			header: group.id === null ? undefined : group.name,
			selectAll: group.id !== null
		} );
		groupWidget.connect( this, {
			toggleSuggestion: [ 'emit', 'toggleSelection' ],
			selectAll: [ 'emit', 'selectAll', group.id ],
			removeAll: [ 'emit', 'removeAll', group.id ],
			expand: [ 'emit', 'expand' ]
		} );

		this.suggestions = this.suggestions.concat( suggestionWidgets );
		this.suggestionGroupWidgets.push( groupWidget );
		this.$element.append( groupWidget.$element );
	}

	this.$element
		.addClass( 'mw-ge-TopicSelectionWidget' );
}

OO.inheritClass( TopicSelectionWidget, OO.ui.Widget );

/**
 * Get the IDs of all selected topics, both those that are visible and selected, and those that are
 * hidden and were preselected.
 *
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
 * @param {string[]} topics IDs of topics to mark as selected
 */
TopicSelectionWidget.prototype.setSelectedTopics = function ( topics ) {
	this.suggestions.forEach( function ( suggestion ) {
		suggestion.confirmed = topics.indexOf( suggestion.suggestionData.id ) !== -1;
	} );
};

/**
 * @return {SuggestionWidget[]}
 */
TopicSelectionWidget.prototype.getSuggestions = function () {
	return this.suggestions;
};

/**
 * @return {mw.libs.ge.TopicFilters}
 */
TopicSelectionWidget.prototype.getFilters = function () {
	return new TopicFilters( {
		topics: this.getSelectedTopics(),
		topicsMatchMode: this.matchModeSelector ? this.matchModeSelector.getSelectedMode() : null
	} );
};

/**
 * @param {mw.libs.ge.TopicFilters} filters Filters to apply
 */
TopicSelectionWidget.prototype.setFilters = function ( filters ) {
	this.setSelectedTopics( filters.getTopics() );
	if ( this.matchModeSelector ) {
		this.matchModeSelector.setSelectedMode( filters.getTopicsMatchMode() );
	}
};

module.exports = TopicSelectionWidget;
