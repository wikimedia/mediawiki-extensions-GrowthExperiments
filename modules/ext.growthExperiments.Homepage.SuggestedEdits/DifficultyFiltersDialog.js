'use strict';

var FiltersDialog = require( './FiltersDialog.js' ),
	TaskTypeSelectionWidget = require( './TaskTypeSelectionWidget.js' ),
	CONSTANTS = require( 'ext.growthExperiments.DataStore' ).CONSTANTS,
	SUGGESTED_EDITS_CONFIG = CONSTANTS.SUGGESTED_EDITS_CONFIG,
	ALL_TASK_TYPES = CONSTANTS.ALL_TASK_TYPES;

/**
 * Class for handling UI changes to difficulty filters.
 *
 * @inheritDoc
 *
 * @class mw.libs.ge.DifficultyFiltersDialog
 * @extends mw.libs.ge.FiltersDialog
 *
 * @param {mw.libs.ge.DataStore} rootStore
 */
function DifficultyFiltersDialog( rootStore ) {
	DifficultyFiltersDialog.super.call( this );
	this.filtersStore = rootStore.newcomerTasks.filters;
}

OO.inheritClass( DifficultyFiltersDialog, FiltersDialog );

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

/** @inheritDoc **/
DifficultyFiltersDialog.prototype.initialize = function () {
	DifficultyFiltersDialog.super.prototype.initialize.call( this );

	this.taskTypeSelector = new TaskTypeSelectionWidget( {
		selectedTaskTypes: this.filtersStore.preferences.taskTypes,
		introLinks: SUGGESTED_EDITS_CONFIG.GEHomepageSuggestedEditsIntroLinks
	}, ALL_TASK_TYPES ).connect( this, { select: 'onTaskTypeSelect' } );
	this.content.$element.append( this.taskTypeSelector.$element );
	this.$body.append( this.content.$element );
	this.$foot.append( this.footerPanelLayout.$element );
};

/**
 * Return an array of enabled task types to use for searching.
 *
 * @override
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
	this.filtersStore.setSelectedTaskTypes( this.getEnabledFilters() );
	this.emit( 'search', selected );
};

/** @override **/
DifficultyFiltersDialog.prototype.savePreferences = function () {
	this.filtersStore.setSelectedTaskTypes( this.getEnabledFilters() );
	this.filtersStore.savePreferences();
};

/** @override **/
DifficultyFiltersDialog.prototype.updateFiltersFromState = function () {
	this.taskTypeSelector.setSelected( this.filtersStore.preferences.taskTypes );
};

/** @inheritDoc **/
DifficultyFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return DifficultyFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateFiltersFromState();
			this.actions.get()[ 0 ].setDisabled( !this.getEnabledFilters().length );
		}, this );
};

module.exports = DifficultyFiltersDialog;
