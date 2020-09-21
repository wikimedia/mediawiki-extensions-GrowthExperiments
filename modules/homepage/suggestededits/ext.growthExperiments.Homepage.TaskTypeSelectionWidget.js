/**
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {Object} config
 * @param {string[]} config.selectedTaskTypes Pre-selected task types
 */
function TaskTypeSelectionWidget( config ) {
	// Parent constructor
	TaskTypeSelectionWidget.super.call( this, config );

	this.buildCheckboxFilters( config.selectedTaskTypes );
	this.$element.append(
		this.makeHeadersForDifficulty( 'easy' ),
		this.easyFilters.$element,
		this.makeHeadersForDifficulty( 'medium' ),
		this.mediumFilters.$element,
		this.makeHeadersForDifficulty( 'hard' ),
		this.hardFilters.$element
	);
}

OO.inheritClass( TaskTypeSelectionWidget, OO.ui.Widget );

/**
 * Return an array of enabled task types to use for searching.
 *
 * @return {string[]}
 */
TaskTypeSelectionWidget.prototype.getSelected = function () {
	return this.easyFilters.findSelectedItemsData()
		.concat( this.mediumFilters.findSelectedItemsData() )
		.concat( this.hardFilters.findSelectedItemsData() );
};

TaskTypeSelectionWidget.prototype.onSelect = function () {
	this.emit( 'select', this.getSelected() );
};

/**
 * Select the given task types.
 *
 * @param {string[]} taskTypes
 */
TaskTypeSelectionWidget.prototype.setSelected = function ( taskTypes ) {
	this.easyFilters.selectItemsByData( taskTypes );
	this.mediumFilters.selectItemsByData( taskTypes );
	this.hardFilters.selectItemsByData( taskTypes );
};

TaskTypeSelectionWidget.prototype.buildCheckboxFilters = function ( selectedTaskTypes ) {
	var introLinks = require( './config.json' ).GEHomepageSuggestedEditsIntroLinks;

	this.createFilter = this.makeCheckbox( {
		id: 'create',
		difficulty: 'hard',
		messages: {
			label: mw.message( 'growthexperiments-homepage-suggestededits-tasktype-label-create' ).text()
		},
		disabled: true
	}, false );
	this.createFilter.$element.append( $( '<div>' )
		.addClass( 'suggested-edits-create-article-additional-msg' )
		.html(
			mw.message( 'growthexperiments-homepage-suggestededits-create-article-additional-message' )
				.params( [ mw.user, mw.util.getUrl( introLinks.create ) ] )
				.parse()
		)
	);

	this.easyFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'easy', selectedTaskTypes )
	} ).connect( this, { select: 'onSelect' } );

	this.mediumFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'medium', selectedTaskTypes )
	} ).connect( this, { select: 'onSelect' } );

	this.hardFilters = new OO.ui.CheckboxMultiselectWidget( {
		items: this.makeCheckboxesForDifficulty( 'hard', selectedTaskTypes )
			.concat( [ this.createFilter ] )
	} ).connect( this, { select: 'onSelect' } );
};

/**
 * @param {string} difficulty 'easy', 'medium' or 'hard'
 * @return {jQuery}
 */
TaskTypeSelectionWidget.prototype.makeHeadersForDifficulty = function ( difficulty ) {
	// The following icons are used here:
	// * difficulty-easy
	// * difficulty-medium
	// * difficulty-hard
	var iconWidget = new OO.ui.IconWidget( { icon: 'difficulty-' + difficulty } ),
		$label = $( '<h4>' )
			.addClass( 'suggested-edits-difficulty-level-label' )
			.text( mw.message(
				// The following messages are used here:
				// * growthexperiments-homepage-startediting-dialog-difficulty-level-easy-label
				// * growthexperiments-homepage-startediting-dialog-difficulty-level-medium-label
				// * growthexperiments-homepage-startediting-dialog-difficulty-level-hard-label
				'growthexperiments-homepage-startediting-dialog-difficulty-level-' + difficulty + '-label'
			).text() ),
		$description = $( '<p>' )
			.addClass( 'suggested-edits-difficulty-level-desc' )
			.text( mw.message(
				// The following messages are used here:
				// * growthexperiments-homepage-startediting-dialog-difficulty-level-easy-description-header
				// * growthexperiments-homepage-startediting-dialog-difficulty-level-medium-description-header
				// * growthexperiments-homepage-startediting-dialog-difficulty-level-hard-description-header
				'growthexperiments-homepage-startediting-dialog-difficulty-level-' + difficulty + '-description-header'
			).params( [ mw.user ] ).text() );
	return iconWidget.$element.add( $label ).add( $description );
};

/**
 * @param {string} difficulty 'easy', 'medium' or 'hard'
 * @param {string[]} selectedTaskTypes Pre-selected task types
 * @return {OO.ui.CheckboxMultioptionWidget[]}
 */
TaskTypeSelectionWidget.prototype.makeCheckboxesForDifficulty = function ( difficulty, selectedTaskTypes ) {
	var taskType,
		taskTypes = require( './TaskTypes.json' ),
		checkboxes = [];
	for ( taskType in taskTypes ) {
		if ( taskTypes[ taskType ].difficulty === difficulty ) {
			checkboxes.push( this.makeCheckbox(
				taskTypes[ taskType ],
				selectedTaskTypes.indexOf( taskTypes[ taskType ].id ) !== -1
			) );
		}
	}
	return checkboxes;
};

/**
 * @param {Object} taskTypeData
 * @param {boolean} selected
 * @return {OO.ui.CheckboxMultioptionWidget}
 */
TaskTypeSelectionWidget.prototype.makeCheckbox = function ( taskTypeData, selected ) {
	return new OO.ui.CheckboxMultioptionWidget( {
		data: taskTypeData.id,
		label: taskTypeData.messages.label,
		selected: !!selected,
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

module.exports = TaskTypeSelectionWidget;
