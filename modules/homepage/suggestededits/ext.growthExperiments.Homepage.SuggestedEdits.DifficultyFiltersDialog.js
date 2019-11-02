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
		flags: [ 'safe' ]
	}
];

DifficultyFiltersDialog.prototype.initialize = function () {
	var introLinks = require( './config.json' ).GEHomepageSuggestedEditsIntroLinks;
	DifficultyFiltersDialog.super.prototype.initialize.call( this );

	this.enabledFilters = {};
	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.footerPanelLayout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	this.easyFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'easy' )
	} ).connect( this, { select: 'onSelect' } );

	this.mediumFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'medium' )
	} ).connect( this, { select: 'onSelect' } );

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
	} ).connect( this, { select: 'onSelect' } );

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

DifficultyFiltersDialog.prototype.onSelect = function () {
	this.enabledFilters = this.easyFilters.findSelectedItemsData()
		.concat( this.mediumFilters.findSelectedItemsData() )
		.concat( this.hardFilters.findSelectedItemsData() );
	if ( this.enabledFilters.length ) {
		this.emit( 'search', this.enabledFilters );
		this.actions.get()[ 0 ].setDisabled( false );
	} else {
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

DifficultyFiltersDialog.prototype.getActionProcess = function ( action ) {
	return DifficultyFiltersDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				this.close();
			}
		}, this );
};

module.exports = DifficultyFiltersDialog;
