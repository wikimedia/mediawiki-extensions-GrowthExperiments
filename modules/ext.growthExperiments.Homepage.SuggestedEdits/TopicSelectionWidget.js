const SuggestionWidget = require( './SuggestionWidget.js' ),
	SuggestionGroupWidget = require( './SuggestionGroupWidget.js' ),
	MatchModeSelectWidget = require( './MatchModeSelectWidget.js' ),
	TopicFilters = require( '../ext.growthExperiments.DataStore/TopicFilters.js' ),
	CONSTANTS = require( 'ext.growthExperiments.DataStore' ).CONSTANTS,
	TOPIC_MATCH_MODES = CONSTANTS.TOPIC_MATCH_MODES,
	TOPIC_MATCH_MODE_OPTIONS = [
		{
			data: TOPIC_MATCH_MODES.OR,
			label: mw.message( 'growthexperiments-homepage-suggestededits-topics-match-mode-any' ).text()
		},
		{
			data: TOPIC_MATCH_MODES.AND,
			label: mw.message( 'growthexperiments-homepage-suggestededits-topics-match-mode-all' ).text()
		}
	];

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
 * @class mw.libs.ge.TopicSelectionWidget
 * @param {Object} config
 * @param {number} [config.initialLimit=12] Number of topics to display initially; use Infinity to disable
 * @param {mw.libs.ge.TopicFilters} [config.filters=new TopicFilters()] Initially selected topic filters
 * @param {jQuery|true} [config.$overlay] Overlay to display the widget in, or true to use default OOUI window
 * @param {Object} GROUPED_TOPICS Topics to show, organized by group
 */
function TopicSelectionWidget( config, GROUPED_TOPICS ) {
	config = Object.assign( {
		initialLimit: 12,
		filters: new TopicFilters()
	}, config );

	// Parent constructor
	TopicSelectionWidget.super.call( this, config );

	if ( !GROUPED_TOPICS ) {
		this.suggestions = [];
		return;
	}

	if ( config.isMatchModeEnabled ) {
		this.matchModeSelector = new MatchModeSelectWidget( {
			classes: [ 'mw-ge-TopicSelectionWidget__match-mode' ],
			options: TOPIC_MATCH_MODE_OPTIONS,
			initialValue: config.filters.getTopicsMatchMode() || TOPIC_MATCH_MODES.OR,
			$overlay: config.$overlay
		} );
		this.matchModeSelector.connect( this, {
			toggleMatchMode: [ 'emit', 'toggleMatchMode' ]
		} );
		this.$element.append( this.matchModeSelector.$element );
	}

	this.suggestions = [];
	this.suggestionGroupWidgets = [];
	for ( const key in GROUPED_TOPICS ) {
		const group = GROUPED_TOPICS[ key ];
		const suggestionWidgets = group.topics.map( ( topic ) => new SuggestionWidget( { suggestionData: {
			id: topic.id,
			text: topic.name,
			confirmed: config.filters.getTopics().includes( topic.id )
		} } ) );
		let displayedSuggestionWidgets = suggestionWidgets;
		let hiddenSuggestionWidgets = [];

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
			const anyHiddenSelected = hiddenSuggestionWidgets.some( ( suggestion ) => suggestion.confirmed );
			if ( anyHiddenSelected ) {
				displayedSuggestionWidgets = suggestionWidgets;
				hiddenSuggestionWidgets = [];
			}
		}

		const groupWidget = new SuggestionGroupWidget( {
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
		.filter( ( suggestion ) => suggestion.confirmed )
		.map( ( suggestion ) => suggestion.suggestionData.id );
};

/**
 * @param {string[]} topics IDs of topics to mark as selected
 */
TopicSelectionWidget.prototype.setSelectedTopics = function ( topics ) {
	this.suggestions.forEach( ( suggestion ) => {
		suggestion.confirmed = topics.includes( suggestion.suggestionData.id );
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
