'use strict';

var DifficultyFiltersDialog = function DifficultyFiltersDialog( config ) {
	DifficultyFiltersDialog.super.call( this, config );
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
		items: [
			new OO.ui.CheckboxMultioptionWidget( {
				data: 'copyedit',
				label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-label-copyedit' ),
				selected: true
			} ),
			new OO.ui.CheckboxMultioptionWidget( {
				data: 'links',
				label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-label-links' ),
				selected: true
			} )
		]
	} ).connect( this, { select: 'onSelect' } );

	this.mediumFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: [
			new OO.ui.CheckboxMultioptionWidget( {
				data: 'references',
				label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-label-references' )
			} ),
			new OO.ui.CheckboxMultioptionWidget( {
				data: 'update',
				label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-label-update' )
			} )
		]
	} ).connect( this, { select: 'onSelect' } );

	this.createFilter = new OO.ui.CheckboxMultioptionWidget( {
		data: 'create',
		classes: [ 'suggested-edits-create-article' ],
		label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-label-create' ),
		disabled: true
	} );
	this.createFilter.$element.append( $( '<div>' )
		.addClass( 'suggested-edits-create-article-additional-msg' )
		.text( mw.message( 'growthexperiments-homepage-suggestededits-create-article-additional-message' ).text() )
	);

	this.hardFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: [
			new OO.ui.CheckboxMultioptionWidget( {
				data: 'expand',
				label: mw.msg( 'growthexperiments-homepage-suggestededits-tasktype-label-expand' )
			} ),
			this.createFilter
		]
	} ).connect( this, { select: 'onSelect' } );

	this.content.$element.append(
		new OO.ui.IconWidget( { icon: 'difficulty-easy' } ).$element,
		$( '<h4>' )
			.addClass( 'suggested-edits-difficulty-level-label' )
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label' ).text() ),
		$( '<p>' )
			.addClass( 'suggested-edits-difficulty-level-desc' )
			.text( mw.message( 'growthexperiments-homepage-startediting-dialog-difficulty-level-easy-description-header' )
				.params( mw.user )
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
				.params( mw.user )
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
				.params( mw.user )
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
