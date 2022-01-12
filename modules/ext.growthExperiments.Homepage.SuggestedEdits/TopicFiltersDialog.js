'use strict';

var FiltersDialog = require( './FiltersDialog.js' ),
	TopicSelectionWidget = require( './TopicSelectionWidget.js' );

/**
 * Class for handling UI changes to topic filters.
 *
 * @inheritDoc
 *
 * @class mw.libs.ge.TopicFiltersDialog
 * @extends mw.libs.ge.FiltersDialog
 */
function TopicFiltersDialog( config ) {
	TopicFiltersDialog.super.call( this, config );
	this.config = config;
	this.updating = false;
	this.performSearchUpdateActionsDebounced =
		OO.ui.debounce( this.performSearchUpdateActions.bind( this ) );
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
	this.footerPanelLayout
		.toggle( false )
		.$element.append( this.articleCounter.$element );

	this.buildTopicFilters();
	this.$body.append( this.content.$element );
	this.$foot.append( this.footerPanelLayout.$element );
	this.$element.addClass( 'mw-ge-topic-filters-dialog' );
};

TopicFiltersDialog.prototype.buildTopicFilters = function () {
	var $topicSelectorWrapper;
	this.content.$element.empty();
	this.content.$element.append( this.errorMessage.$element );
	this.topicSelector = new TopicSelectionWidget( { selectedTopics: this.config.presets || [] } );
	this.topicSelector.connect( this, {
		// selectAll and removeAll forward a single topic group ID argument
		selectAll: [ 'emit', 'selectAll' ],
		removeAll: [ 'emit', 'removeAll' ],
		expand: [ 'emit', 'expand' ],
		toggleSelection: 'onTopicSelectorToggleSelection'
	} );
	$topicSelectorWrapper = $( '<div>' )
		.addClass( 'suggested-edits-topic-filters-topic-selector' )
		.append(
			$( '<h4>' )
				.addClass( 'mw-ge-topic-filters-dialog-intro-topic-selector-header' )
				.text( mw.message(
					'growthexperiments-homepage-topic-filters-dialog-intro-topic-selector-header'
				).text() ),
			$( '<p>' )
				.addClass( 'mw-ge-topic-filters-dialog-intro-topic-selector-subheader' )
				.text( mw.message(
					'growthexperiments-homepage-topic-filters-dialog-intro-topic-selector-subheader'
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
	// this.config.presets could be null ((e.g. user just initiated the module, see T238611#5800350)
	var presets = this.config.presets || [];
	// Prevent 'search' events from being fired by performSearchUpdateActions()
	this.updating = true;
	this.topicSelector.suggestions.forEach( function ( suggestion ) {
		suggestion.toggleSuggestion( presets.indexOf( suggestion.suggestionData.id ) > -1 );
	} );
	this.updating = false;
};

/**
 * Return an array of enabled topic types to use for searching.
 *
 * @override
 * @return {Object[]}
 */
TopicFiltersDialog.prototype.getEnabledFilters = function () {
	// Topic selection widget may not yet be initialized (when the module
	// is loading initially) in which case use the presets.
	return this.topicSelector ? this.topicSelector.getSelectedTopics() : this.config.presets;
};

/**
 * Perform a search with enabled filters, if any.
 */
TopicFiltersDialog.prototype.performSearchUpdateActions = function () {
	// Don't fire 'search' events for changes that we made ourselves in updateFiltersFromState()
	if ( !this.updating ) {
		this.articleCounter.setSearching();
		this.emit( 'search', null, this.getEnabledFilters() );
	}
};

/**
 * Set and save topic filter preferences for the user.
 *
 * @override
 * @return {jQuery.Promise}
 */
TopicFiltersDialog.prototype.savePreferences = function () {
	// If existing preference is null, that means the user never saved a change
	// to the topics, so we should continue to save null. Otherwise for empty filters
	// save a JSON encoded empty array..
	var prefName = require( './config.json' ).GENewcomerTasksTopicFiltersPref,
		prefValueHasBeenSetBefore = mw.user.options.get( prefName ),
		prefValue;
	if ( !this.getEnabledFilters().length ) {
		prefValue = prefValueHasBeenSetBefore ? JSON.stringify( [] ) : null;
	} else {
		prefValue = JSON.stringify( this.getEnabledFilters() );
	}
	this.config.presets = prefValue;
	mw.user.options.set( prefName, prefValue );
	return new mw.Api().saveOption( prefName, prefValue );
};

/** @inheritDoc **/
TopicFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return TopicFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateFiltersFromState();
		}, this );
};

TopicFiltersDialog.prototype.onTopicSelectorToggleSelection = function () {
	// Don't fire 'search' events for changes that we made ourselves in updateFiltersFromState()
	if ( !this.updating ) {
		// The "select all" buttons fire many toggleSelection events at once, so debounce them
		this.performSearchUpdateActionsDebounced();
	}
};

module.exports = TopicFiltersDialog;
