'use strict';

var DifficultyFiltersDialog,
	ArticleCountWidget = require( './ArticleCountWidget.js' ),
	TaskTypeSelectionWidget = require( './TaskTypeSelectionWidget.js' );

/**
 * Class for handling UI changes to difficulty filters.
 *
 * Emits the following OOJS events:
 * - search: when the filter selection changes. First argument is the list of selected filters.
 * - done: when the dialog is closed (saved). First argument is the list of selected filters.
 * On canceling the dialog, it will emit a search event with the original (pre-opening) filter list
 * if it differs from the filter list at closing, and the filter list at closing is not empty.
 *
 * Expects updateMatchCount() to be called back with the number of matches after emitting
 * a search event.
 *
 * @class DifficultyFiltersDialog
 * @param {Object} config
 * @param {Array} config.presets List of enabled task types. Will be updated on close.
 */
DifficultyFiltersDialog = function ( config ) {
	DifficultyFiltersDialog.super.call( this, config );
	this.config = config;
};
OO.inheritClass( DifficultyFiltersDialog, OO.ui.ProcessDialog );

DifficultyFiltersDialog.static.name = 'difficultyfilters';
DifficultyFiltersDialog.static.size = 'medium';
DifficultyFiltersDialog.static.title = mw.msg( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' );

DifficultyFiltersDialog.static.actions = [
	{
		label: mw.msg( 'growthexperiments-homepage-suggestededits-difficulty-filters-close' ),
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

DifficultyFiltersDialog.prototype.initialize = function () {
	DifficultyFiltersDialog.super.prototype.initialize.call( this );

	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.footerPanelLayout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.taskTypeSelector = new TaskTypeSelectionWidget( {
		selectedTaskTypes: this.config.presets,
		introLinks: require( './config.json' ).GEHomepageSuggestedEditsIntroLinks
	} ).connect( this, { select: 'onTaskTypeSelect' } );
	this.content.$element.append( this.taskTypeSelector.$element );
	this.articleCounter = new ArticleCountWidget();
	this.footerPanelLayout
		.toggle( false )
		.$element.append( this.articleCounter.$element );

	this.$body.append( this.content.$element );
	this.$foot.append( this.footerPanelLayout.$element );
};

/**
 * Return an array of enabled task types to use for searching.
 *
 * @return {string[]}
 */
DifficultyFiltersDialog.prototype.getEnabledFilters = function () {
	return this.taskTypeSelector.getSelected();
};

/**
 * Perform a search if enabled filters exist, otherwise disable Done action and show error message.
 *
 * @param {string[]} selected
 */
DifficultyFiltersDialog.prototype.onTaskTypeSelect = function ( selected ) {
	var actions = this.actions.get();
	if ( actions.length && actions[ 0 ] ) {
		actions[ 0 ].setDisabled( selected.length === 0 );
	}
	this.articleCounter.setSearching();
	this.emit( 'search', selected );
};

DifficultyFiltersDialog.prototype.updateMatchCount = function ( count ) {
	this.articleCounter.setCount( count );
	this.footerPanelLayout.toggle( true );
};

DifficultyFiltersDialog.prototype.savePreferences = function () {
	var enabledFilters = this.getEnabledFilters();
	this.config.presets = enabledFilters;
	return new mw.Api().saveOption(
		'growthexperiments-homepage-se-filters',
		JSON.stringify( enabledFilters )
	);
};

DifficultyFiltersDialog.prototype.getActionProcess = function ( action ) {
	return DifficultyFiltersDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				this.savePreferences();
				this.config.presets = this.getEnabledFilters();
				this.emit( 'done', this.config.presets );
				this.close( { action: 'done' } );
			}
			if ( action === 'cancel' ) {
				this.updateFiltersFromState();
				this.emit( 'cancel' );
				this.close( { action: 'cancel' } );
			}
		}, this );
};

DifficultyFiltersDialog.prototype.updateFiltersFromState = function () {
	this.taskTypeSelector.setSelected( this.config.presets );
};

DifficultyFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return DifficultyFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateFiltersFromState();
			this.actions.get()[ 0 ].setDisabled( !this.getEnabledFilters().length );
		}, this );
};

module.exports = DifficultyFiltersDialog;
