'use strict';

var TopicFiltersDialog,
	TopicSelectionWidget = require( 'ext.growthExperiments.Homepage.TopicSelectionWidget' );

/**
 * Class for handling UI changes to topic filters.
 *
 * Emits the following OOJS events:
 * - search: when the filter selection changes. First argument is null (task types will be retrieved
 *   by the listener), and the second argument is the list of selected filters.
 * - done: when the dialog is closed (saved). First argument is null (task types will be retrieved
 *   by the listener), and the second argument is the list of selected filters.
 * On canceling the dialog, it will emit a search event with the original (pre-opening) filter list
 * if it differs from the filter list at closing, and the filter list at closing is not empty.
 *
 * Expects updateMatchCount() to be called back with the number of matches after emitting
 * a search event.
 *
 * @class TopicFiltersDialog
 * @param {Object} config
 * @param {string[]} config.presets List of enabled topics. Will be updated on close.
 */
TopicFiltersDialog = function ( config ) {
	TopicFiltersDialog.super.call( this, config );
	this.config = config;
	this.articleCountNumber = 0;
};
OO.inheritClass( TopicFiltersDialog, OO.ui.ProcessDialog );

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

TopicFiltersDialog.prototype.initialize = function () {
	TopicFiltersDialog.super.prototype.initialize.call( this );

	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.footerPanelLayout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.errorMessage = new OO.ui.MessageWidget( {
		type: 'error',
		inline: true,
		classes: [ 'suggested-edits-filters-error' ],
		label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-error' ).text()
	} ).toggle( false );
	this.articleCountLabel = new OO.ui.LabelWidget( { classes: [ 'suggested-edits-article-count' ] } );
	this.footerPanelLayout
		.toggle( false )
		.$element.append(
			new OO.ui.IconWidget( { icon: 'live-broadcast' } ).$element,
			this.articleCountLabel.$element
		);

	this.buildTopicFilters();
	this.$body.append( this.content.$element );
	this.$foot.append( this.footerPanelLayout.$element );
	this.$element.addClass( 'mw-ge-topic-filters-dialog' );
};

TopicFiltersDialog.prototype.buildTopicFilters = function () {
	var $topicSelectorWrapper;
	this.content.$element.empty();
	this.content.$element.append( this.errorMessage.$element );
	this.topicSelector = new TopicSelectionWidget( { selectedTopics: this.config.presets } );
	this.topicSelector.connect( this, {
		expand: 'updateSize',
		toggleSelection: 'performSearchUpdateActions'
	} );
	$topicSelectorWrapper = $( '<div>' )
		.addClass( 'suggested-edits-topic-filters-topic-selector' )
		.append(
			$( '<h4>' )
				.addClass( 'mw-ge-topic-filters-dialog-intro-topic-selector-header' )
				.text( mw.message( 'growthexperiments-homepage-topic-filters-dialog-intro-topic-selector-header' ).text() ),
			$( '<p>' )
				.addClass( 'mw-ge-topic-filters-dialog-intro-topic-selector-subheader' )
				.text( mw.message( 'growthexperiments-homepage-topic-filters-dialog-intro-topic-selector-subheader' ).text() ),
			this.topicSelector.$element
		);
	this.content.$element.append( $topicSelectorWrapper );
};

TopicFiltersDialog.prototype.updateTopicFiltersFromState = function () {
	this.topicSelector.suggestions.forEach( function ( suggestion ) {
		suggestion.confirmed = this.config.presets.indexOf( suggestion.suggestionData.id ) > -1;
		suggestion.update();
	}.bind( this ) );
};

/**
 * Return an array of enabled topic types to use for searching.
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
	this.emit( 'search', null, this.getEnabledFilters() );
};

TopicFiltersDialog.prototype.updateMatchCount = function ( count ) {
	this.articleCountNumber = Number( count );
	this.articleCountLabel
		.setLabel( new OO.ui.HtmlSnippet(
			mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-article-count' )
				.params( [ mw.language.convertNumber( this.articleCountNumber ) ] )
				.parse()
		) );
	this.footerPanelLayout.toggle( true );
};

TopicFiltersDialog.prototype.savePreferences = function () {
	return new mw.Api().saveOption( 'growthexperiments-homepage-se-topic-filters',
		this.getEnabledFilters().length > 0 ? JSON.stringify( this.getEnabledFilters() ) : null );
};

TopicFiltersDialog.prototype.getActionProcess = function ( action ) {
	return TopicFiltersDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				this.savePreferences();
				this.config.presets = this.getEnabledFilters();
				this.emit( 'done', this.config.presets );
				this.close( { action: 'done' } );
			}
			if ( action === 'cancel' ) {
				// FIXME: Back up the task queue and pager and restore
				// previous state if user cancels.
				if ( JSON.stringify( this.getEnabledFilters() ) !==
					JSON.stringify( this.config.presets ) ) {
					// User has canceled and the filters they interacted with
					// differ from what they had selected when the dialog opened,
					// so perform a search with their original settings.
					this.emit( 'search', null, this.config.presets );
				}
				this.close( { action: 'cancel' } );
			}
		}, this );
};

TopicFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return TopicFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateTopicFiltersFromState();
		}, this );
};

module.exports = TopicFiltersDialog;
