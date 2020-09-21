'use strict';

var DifficultyFiltersDialog,
	ArticleCountWidget = require( './ext.growthExperiments.Homepage.ArticleCountWidget.js' ),
	taskTypes = require( './TaskTypes.json' );

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
	this.errorMessage = new OO.ui.MessageWidget( {
		type: 'error',
		inline: true,
		classes: [ 'suggested-edits-filters-error' ],
		label: mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-error' ).text()
	} ).toggle( false );
	this.articleCounter = new ArticleCountWidget();
	this.footerPanelLayout
		.toggle( false )
		.$element.append( this.articleCounter.$element );

	this.buildCheckboxFilters();
	this.$body.append( this.content.$element );
	this.$foot.append( this.footerPanelLayout.$element );
};

DifficultyFiltersDialog.prototype.buildCheckboxFilters = function () {
	var introLinks = require( './config.json' ).GEHomepageSuggestedEditsIntroLinks;
	this.content.$element.empty();
	this.content.$element.append( this.errorMessage.$element );
	this.easyFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'easy' )
	} ).connect( this, { select: 'performSearchUpdateActions' } );

	this.mediumFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'medium' )
	} ).connect( this, { select: 'performSearchUpdateActions' } );

	this.createFilter = this.makeCheckbox( {
		id: 'create',
		difficulty: 'hard',
		messages: {
			label: mw.message( 'growthexperiments-homepage-suggestededits-tasktype-label-create' ).text()
		},
		disabled: true
	} );
	this.createFilter.$element.append( $( '<div>' )
		.addClass( 'suggested-edits-create-article-additional-msg' )
		.html(
			mw.message( 'growthexperiments-homepage-suggestededits-create-article-additional-message' )
				.params( [ mw.user, mw.util.getUrl( introLinks.create ) ] )
				.parse()
		)
	);

	this.hardFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'hard' )
			.concat( [ this.createFilter ] )
	} ).connect( this, { select: 'performSearchUpdateActions' } );

	this.content.$element.append(
		new OO.ui.IconWidget( { icon: 'difficulty-easy' } ).$element,
		$( '<h4>' )
			.addClass( 'suggested-edits-difficulty-level-label' )
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label' ).text() ),
		$( '<p>' )
			.addClass( 'suggested-edits-difficulty-level-desc' )
			.text(
				mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-easy-description-header' )
					.params( [ mw.user ] )
					.text() )
	);
	this.content.$element.append( this.easyFilters.$element );

	this.content.$element.append(
		new OO.ui.IconWidget( { icon: 'difficulty-medium' } ).$element,
		$( '<h4>' )
			.addClass( 'suggested-edits-difficulty-level-label' )
			.text(
				mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-medium-label' ).text()
			),
		$( '<p>' )
			.addClass( 'suggested-edits-difficulty-level-desc' )
			.text(
				mw.message(
					'growthexperiments-homepage-startediting-dialog-difficulty-level-medium-description-header'
				)
					.params( [ mw.user ] )
					.text() )
	);
	this.content.$element.append( this.mediumFilters.$element );

	this.content.$element.append(
		new OO.ui.IconWidget( { icon: 'difficulty-hard' } ).$element,
		$( '<h4>' )
			.addClass( 'suggested-edits-difficulty-level-label' )
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-hard-label' ).text() ),
		$( '<p>' )
			.addClass( 'suggested-edits-difficulty-level-desc' )
			.text(
				mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-hard-description-header' )
					.params( [ mw.user ] )
					.text() )
	);
	this.content.$element.append( this.hardFilters.$element );
};

/**
 * Return an array of enabled task types to use for searching.
 *
 * @return {Object[]}
 */
DifficultyFiltersDialog.prototype.getEnabledFilters = function () {
	return this.easyFilters.findSelectedItemsData()
		.concat( this.mediumFilters.findSelectedItemsData() )
		.concat( this.hardFilters.findSelectedItemsData() );
};

/**
 * Perform a search if enabled filters exist, otherwise disable Done action.
 */
DifficultyFiltersDialog.prototype.performSearchUpdateActions = function () {
	this.emit( 'search', this.getEnabledFilters() );
	if ( this.getEnabledFilters().length ) {
		this.actions.get()[ 0 ].setDisabled( false );
		this.errorMessage.toggle( false );
	} else {
		this.errorMessage.toggle( true );
		this.actions.get()[ 0 ].setDisabled( true );
	}
};

/**
 * @param {string} difficulty 'easy', 'medium' or 'hard'
 * @return {OO.ui.CheckboxMultioptionWidget[]}
 */
DifficultyFiltersDialog.prototype.makeCheckboxesForDifficulty = function ( difficulty ) {
	var taskType,
		checkboxes = [];
	for ( taskType in taskTypes ) {
		if ( taskTypes[ taskType ].difficulty === difficulty ) {
			checkboxes.push( this.makeCheckbox( taskTypes[ taskType ] ) );
		}
	}
	return checkboxes;
};

/**
 * @param {Object} taskTypeData
 * @return {OO.ui.CheckboxMultioptionWidget}
 */
DifficultyFiltersDialog.prototype.makeCheckbox = function ( taskTypeData ) {
	return new OO.ui.CheckboxMultioptionWidget( {
		data: taskTypeData.id,
		label: taskTypeData.messages.label,
		selected: this.config.presets.indexOf( taskTypeData.id ) >= 0,
		disabled: !!taskTypeData.disabled,
		// The following classes are used here:
		// * suggested-edits-checkbox-copyedit
		// * suggested-edits-checkbox-create
		// * suggested-edits-checkbox-expand
		// * suggested-edits-checkbox-links
		// * suggested-edits-checkbox-update
		classes: [ 'suggested-edits-checkbox-' + taskTypeData.id ]
	} );
};

DifficultyFiltersDialog.prototype.updateMatchCount = function ( count ) {
	this.articleCounter.setCount( count );
	this.footerPanelLayout.toggle( true );
};

DifficultyFiltersDialog.prototype.savePreferences = function () {
	return new mw.Api().saveOption(
		'growthexperiments-homepage-se-filters',
		JSON.stringify( this.getEnabledFilters() )
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
	this.easyFilters.selectItemsByData( this.config.presets );
	this.mediumFilters.selectItemsByData( this.config.presets );
	this.hardFilters.selectItemsByData( this.config.presets );
};

DifficultyFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return DifficultyFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.updateFiltersFromState();
			this.actions.get()[ 0 ].setDisabled( !this.getEnabledFilters().length );
		}, this );
};

module.exports = DifficultyFiltersDialog;
