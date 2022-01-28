'use strict';

var FiltersDialog = require( './FiltersDialog.js' ),
	TaskTypeSelectionWidget = require( './TaskTypeSelectionWidget.js' );

/**
 * Class for handling UI changes to difficulty filters.
 *
 * @inheritDoc
 *
 * @class mw.libs.ge.DifficultyFiltersDialog
 * @extends mw.libs.ge.FiltersDialog
 */
function DifficultyFiltersDialog( config ) {
	DifficultyFiltersDialog.super.call( this, config );
	this.config = config;
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
		selectedTaskTypes: this.config.presets,
		introLinks: require( './config.json' ).GEHomepageSuggestedEditsIntroLinks
	} ).connect( this, { select: 'onTaskTypeSelect' } );
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
	this.emit( 'search', selected );
};

/** @override **/
DifficultyFiltersDialog.prototype.savePreferences = function () {
	var enabledFilters = this.getEnabledFilters();
	this.config.presets = enabledFilters;
	return new mw.Api().saveOption(
		'growthexperiments-homepage-se-filters',
		JSON.stringify( enabledFilters )
	);
};

/** @override **/
DifficultyFiltersDialog.prototype.updateFiltersFromState = function () {
	this.taskTypeSelector.setSelected( this.config.presets );
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
