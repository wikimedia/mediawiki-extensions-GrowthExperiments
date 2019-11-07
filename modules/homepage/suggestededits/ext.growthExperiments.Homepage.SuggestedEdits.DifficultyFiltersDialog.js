'use strict';

var taskTypes = require( './TaskTypes.json' ),
	DifficultyFiltersDialog = function DifficultyFiltersDialog( config ) {
		DifficultyFiltersDialog.super.call( this, config );
		this.config = config;
		this.articleCountNumber = 0;
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
	this.buildCheckboxFilters();
	this.articleCountLabel = new OO.ui.LabelWidget( { classes: [ 'suggested-edits-article-count' ] } );
	this.footerPanelLayout
		.toggle( false )
		.$element.append(
			new OO.ui.IconWidget( { icon: 'live-broadcast' } ).$element,
			this.articleCountLabel.$element
		);

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
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-easy-description-header' )
				.params( [ mw.user ] )
				.text() )
	);
	this.content.$element.append( this.easyFilters.$element );

	this.content.$element.append(
		new OO.ui.IconWidget( { icon: 'difficulty-medium' } ).$element,
		$( '<h4>' )
			.addClass( 'suggested-edits-difficulty-level-label' )
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-medium-label' ).text() ),
		$( '<p>' )
			.addClass( 'suggested-edits-difficulty-level-desc' )
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-medium-description-header' )
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
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-hard-description-header' )
				.params( [ mw.user ] )
				.text() )
	);
	this.content.$element.append( this.hardFilters.$element );
};

/**
 * Return an array of enabled task types to use for searching.
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
		classes: [ 'suggested-edits-checkbox-' + taskTypeData.id ]
	} );
};

DifficultyFiltersDialog.prototype.updateMatchCount = function ( count ) {
	this.articleCountNumber = Number( count );
	this.articleCountLabel
		.setLabel( new OO.ui.HtmlSnippet(
			mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-article-count' )
				.params( [ mw.language.convertNumber( this.articleCountNumber ) ] )
				.parse()
		) );
	this.footerPanelLayout.toggle( true );
};

DifficultyFiltersDialog.prototype.savePreferences = function () {
	return new mw.Api().saveOption( 'growthexperiments-homepage-se-filters', JSON.stringify( this.getEnabledFilters() ) );
};

DifficultyFiltersDialog.prototype.getActionProcess = function ( action ) {
	return DifficultyFiltersDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				this.savePreferences();
				this.config.presets = this.getEnabledFilters();
				this.close();
			}
			if ( action === 'cancel' ) {
				if ( !this.getEnabledFilters().length ) {
					// User has deselected all filters, so ensure they're deselected when dialog
					// re-opens.
					this.config.presets = [];
				}
				if ( JSON.stringify( this.getEnabledFilters() ) !==
					JSON.stringify( this.config.presets ) ) {
					// User has canceled and the filters they interacted with
					// differ from what they had selected when the dialog opened,
					// so perform a search with their original settings.
					this.emit( 'search', this.config.presets );
				}
				this.close();
			}
		}, this );
};

DifficultyFiltersDialog.prototype.getSetupProcess = function ( data ) {
	return DifficultyFiltersDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.buildCheckboxFilters();
		}, this );
};

module.exports = DifficultyFiltersDialog;
