'use strict';

const FiltersDialog = require( './FiltersDialog.js' ),
	TopicSelectionWidget = require( './TopicSelectionWidget.js' ),
	CONSTANTS = require( 'ext.growthExperiments.DataStore' ).CONSTANTS,
	TOPIC_MATCH_MODES = CONSTANTS.TOPIC_MATCH_MODES,
	TopicFilters = require( '../ext.growthExperiments.DataStore/TopicFilters.js' );

/**
 * Class for handling UI changes to topic filters.
 *
 * @inheritDoc
 *
 * @class mw.libs.ge.TopicFiltersDialog
 * @extends mw.libs.ge.FiltersDialog
 *
 * @param {mw.libs.ge.DataStore} rootStore
 */
function TopicFiltersDialog( rootStore ) {
	TopicFiltersDialog.super.call( this );
	this.updating = false;
	this.performSearchUpdateActionsDebounced =
		OO.ui.debounce( this.performSearchUpdateActions.bind( this ) );
	this.filtersStore = rootStore.newcomerTasks.filters;
	this.getTaskCount = rootStore.newcomerTasks.getTaskCount.bind( rootStore.newcomerTasks );
}

OO.inheritClass( TopicFiltersDialog, FiltersDialog );

TopicFiltersDialog.static.name = 'topicfilters';
TopicFiltersDialog.static.size = 'medium';
TopicFiltersDialog.static.title = mw.msg( 'growthexperiments-homepage-suggestededits-topic-filters-title' );

TopicFiltersDialog.static.actions = [
	{
		label: mw.msg( 'growthexperiments-homepage-suggestededits-topic-filters-close' ),
		action: 'close',
		framed: true,
		flags: [ 'primary', 'progressive' ]
	},
	{
		icon: 'close',
		action: 'cancel',
		flags: [ 'safe' ]
	}
];

/** @inheritDoc **/
TopicFiltersDialog.prototype.initialize = function () {
	TopicFiltersDialog.super.prototype.initialize.call( this );

	this.errorMessage = new OO.ui.MessageWidget( {
		type: 'error',
		inline: true,
		classes: [ 'suggested-edits-filters-error' ],
		label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-error' ).text()
	} ).toggle( false );
	this.buildTopicFilters();
	this.$body.append( this.content.$element );
	this.$foot.append( this.footerPanelLayout.$element );
	this.$element.addClass( 'mw-ge-topic-filters-dialog' );
};

TopicFiltersDialog.prototype.buildTopicFilters = function () {
	this.content.$element.empty();
	this.content.$element.append( this.errorMessage.$element );
	this.topicSelector = new TopicSelectionWidget( {
		isMatchModeEnabled: this.filtersStore.shouldUseTopicMatchMode,
		filters: this.filtersStore.preferences.topicFilters || new TopicFilters( {
			topicsMatchMode: TOPIC_MATCH_MODES.OR
		} ),
		$overlay: this.$overlay
	}, this.filtersStore.getGroupedTopics() );
	this.topicSelector.connect( this, {
		// selectAll and removeAll forward a single topic group ID argument
		selectAll: [ 'emit', 'selectAll' ],
		removeAll: [ 'emit', 'removeAll' ],
		expand: [ 'emit', 'expand' ],
		toggleSelection: 'onTopicFilterChange',
		toggleMatchMode: function ( mode ) {
			this.onTopicFilterChange();
			this.emit( 'toggleMatchMode', mode );
		}
	} );
	const $topicSelectorWrapper = $( '<div>' )
		.addClass( 'suggested-edits-topic-filters-topic-selector' )
		.append(
			$( '<h4>' )
				.addClass( 'mw-ge-topic-filters-dialog-intro-topic-selector-header' )
				.text( mw.message(
					'growthexperiments-homepage-topic-filters-dialog-intro-topic-selector-header'
				).text() ),
			this.topicSelector.$element
		);
	this.content.$element.append( $topicSelectorWrapper );
};

/**
 * Toggle the suggestion widget status (checkbox and color) and also
 * expand the suggestion widget if enabled widgets exist below the fold.
 *
 * @override
 */
TopicFiltersDialog.prototype.updateFiltersFromState = function () {
	// filtersStore.preferences.topicFilters could be null ((e.g. user just initiated the module, see T238611#5800350)
	const presets = this.filtersStore.preferences.topicFilters || new TopicFilters( {
		topicsMatchMode: TOPIC_MATCH_MODES.OR
	} );
	// Prevent 'search' events from being fired by performSearchUpdateActions()
	this.updating = true;
	this.topicSelector.suggestions.forEach( ( suggestion ) => {
		suggestion.toggleSuggestion(
			presets.getTopics().includes( suggestion.suggestionData.id )
		);
	} );

	if ( this.topicSelector.matchModeSelector ) {
		this.topicSelector.matchModeSelector.setSelectedMode( presets.getTopicsMatchMode() );
	}
	this.updating = false;
};

/**
 * Return an TopicFilter object with the state of the selector filters.
 *
 * @see modules/ext.growthExperiments.DataStore/constants.js
 * @override
 * @return {mw.libs.ge.TopicFilters|null}
 */
TopicFiltersDialog.prototype.getEnabledFilters = function () {
	// Topic selection widget may not yet be initialized (when the module
	// is loading initially) in which case use the presets.
	return this.topicSelector ? this.topicSelector.getFilters() : this.filtersStore.preferences.topicFilters;
};

/**
 * Perform a search with enabled filters, if any.
 */
TopicFiltersDialog.prototype.performSearchUpdateActions = function () {
	// Don't fire 'search' events for changes that we made ourselves in updateFiltersFromState()
	if ( !this.updating ) {
		this.articleCounter.setSearching();
		this.filtersStore.updateStatesFromTopicsFilters( this.getEnabledFilters() );
		this.emit( 'search', null, this.getEnabledFilters() );
	}
};

/** @override **/
TopicFiltersDialog.prototype.savePreferences = function () {
	this.filtersStore.updateStatesFromTopicsFilters( this.getEnabledFilters() );
	this.filtersStore.savePreferences();
};

/** @inheritDoc **/
TopicFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return TopicFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateFiltersFromState();
			this.updateLoadingState( { isLoading: false, count: this.getTaskCount() } );
		}, this );
};

TopicFiltersDialog.prototype.onTopicFilterChange = function () {
	// Don't fire 'search' events for changes that we made ourselves in updateFiltersFromState()
	if ( !this.updating ) {
		// The "select all" buttons fire many toggleSelection events at once, so debounce them
		this.performSearchUpdateActionsDebounced();
	}
};

module.exports = TopicFiltersDialog;
