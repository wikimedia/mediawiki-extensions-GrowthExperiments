const PostEditToastMessage = require( './PostEditToastMessage.js' );

/**
 * @class mw.libs.ge.TryNewTaskPanel
 * @mixes OO.EventEmitter
 *
 * @constructor
 *
 * @param {Object} config
 * @param {string} config.nextSuggestedTaskType The suggested next task type
 * @param {string} config.activeTaskType The task type the user is currently working on
 * @param {Array} config.tryNewTaskOptOuts List of task type IDs where the user has opted out
 *   from receiving prompts to try a new task type.
 * @param {mw.libs.ge.HelpPanelLogger} config.helpPanelLogger
 */
function TryNewTaskPanel( config ) {
	OO.EventEmitter.call( this );
	this.nextSuggestedTaskType = config.nextSuggestedTaskType;
	this.activeTaskType = config.activeTaskType;
	this.helpPanelLogger = config.helpPanelLogger;
	this.tryNewTaskOptOuts = config.tryNewTaskOptOuts;
	/** @member {OO.ui.CheckboxInputWidget|null} **/
	this.optOutButton = null;
}

OO.initClass( TryNewTaskPanel );
OO.mixinClass( TryNewTaskPanel, OO.EventEmitter );

/**
 * Get the toast message widget to be displayed on top of the panel.
 *
 * @return {OO.ui.MessageWidget}
 */
TryNewTaskPanel.prototype.getPostEditToastMessage = function () {
	return new PostEditToastMessage( {
		icon: 'check',
		type: 'success',
		label: $( '<span>' ).append( mw.message( 'growthexperiments-help-panel-postedit-trynewtask-toast-message' ).parse() ),
		autoHideDuration: 5000,
	} );
};

/**
 * Get the link(s) to display in the footer.
 *
 * @return {Array<jQuery>} A list of footer elements.
 */
TryNewTaskPanel.prototype.getFooterButtons = function () {
	const tryNewTaskButtonWidget = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-help-panel-postedit-trynewtask-try-button-text' ).text(),
		flags: [ 'primary', 'progressive' ],
		classes: [ 'mw-ge-help-panel-postedit-footer', 'mw-ge-help-panel-postedit-footer-trynewtask' ],
	} );
	tryNewTaskButtonWidget.connect( this, { click: 'openPostEditDialogWithNewTask' } );
	const noThanksButtonWidget = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-help-panel-postedit-trynewtask-nothanks-button-text' ).text(),
		framed: false,
		classes: [ 'mw-ge-help-panel-postedit-footer', 'mw-ge-help-panel-postedit-footer-nothanks' ],
	} );
	noThanksButtonWidget.connect( this, { click: 'openPostEditDialog' } );
	return [ new OO.ui.HorizontalLayout( {
		items: [ noThanksButtonWidget, tryNewTaskButtonWidget ],
		classes: [ 'mw-ge-help-panel-postedit-trynewtask-footer' ],
	} ).$element ];
};

/**
 * Log that the "trynewtask" button was clicked and close the panel, passing the next
 * suggested task type so that the close handler knows to use that value when opening the
 * post-edit dialog.
 */
TryNewTaskPanel.prototype.openPostEditDialogWithNewTask = function () {
	this.logAction( 'trynewtask' );
	this.emit( 'close', this.nextSuggestedTaskType );
};

/**
 * Log that the "nothanks" button was clicked and close the panel, passing "null"
 * so that the close handler knows to open the post-edit dialog without any modifications
 * to the task types.
 */
TryNewTaskPanel.prototype.openPostEditDialog = function () {
	this.logAction( 'nothanks' );
	this.emit( 'close', null );
};

TryNewTaskPanel.prototype.getHeaderText = function () {
	return mw.message( 'growthexperiments-help-panel-postedit-trynewtask-header' ).text();
};

/**
 * Log that the panel was closed.
 * Needs to be set up by the (device-dependent) wrapper code that handles displaying the panel.
 */
TryNewTaskPanel.prototype.logClose = function () {
	this.helpPanelLogger.log( 'trynewtask-close', { 'dont-show-again': Number( this.optOutButton.isSelected() ) } );
};

/**
 * Log that the panel was displayed to the user.
 * Needs to be called by the code displaying the panel.
 *
 * @param {Object|undefined} actionData Additional data to pass as action_data to the logger.
 */
TryNewTaskPanel.prototype.logImpression = function ( actionData ) {
	let data = {
		'next-suggested-task-type': this.nextSuggestedTaskType,
		savedTaskType: this.activeTaskType,
	};
	data = Object.assign( data, actionData || {} );
	this.helpPanelLogger.log( 'trynewtask-impression', data );
};

/**
 * Log that one of the footer buttons was clicked.
 * This is handled automatically by the class.
 *
 * @param {string} linkName Symbolic link name ('nothanks' or 'trynewtask').
 */
TryNewTaskPanel.prototype.logAction = function ( linkName ) {
	this.helpPanelLogger.log( 'trynewtask-' + linkName + '-action', { 'dont-show-again': Number( this.optOutButton.isSelected() ) } );
};

/**
 * Get the main area of the panel (the card with a subheader).
 *
 * @return {jQuery|null} The main area, a jQuery object wrapping the card element.
 *   Null if the panel should not have a main area (as no task should be displayed).
 */
TryNewTaskPanel.prototype.getMainArea = function () {
	const $mainArea = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-main' );
	this.optOutButton = new OO.ui.CheckboxInputWidget( {
		selected: false,
		value: 'optOutForTaskType',
	} );
	this.optOutButton.on( 'change', ( isSelected ) => {
		if ( isSelected ) {
			this.tryNewTaskOptOuts.push( this.activeTaskType );
		} else {
			this.tryNewTaskOptOuts = this.tryNewTaskOptOuts.filter( ( taskType ) => taskType !== this.activeTaskType );
		}
		new mw.Api().saveOption(
			'growthexperiments-levelingup-tasktype-prompt-optouts',
			JSON.stringify( this.tryNewTaskOptOuts ),
		);
	} );

	const dismissField = new OO.ui.FieldLayout( this.optOutButton, {
		label: mw.message(
			'growthexperiments-help-panel-postedit-trynewtask-dontshowagain-checkbox',
		).text(),
		align: 'inline',
		classes: [ 'mw-ge-tryNewTaskPanel-dismiss-field' ],
	} );

	// The following messages are used here:
	// * growthexperiments-homepage-suggestededits-tasktype-name-references
	// * growthexperiments-homepage-suggestededits-tasktype-name-copyedit
	// * growthexperiments-homepage-suggestededits-tasktype-name-expand
	// * growthexperiments-homepage-suggestededits-tasktype-name-update
	// * growthexperiments-homepage-suggestededits-tasktype-name-links
	// * growthexperiments-homepage-suggestededits-tasktype-name-link-recommendation
	// * growthexperiments-homepage-suggestededits-tasktype-name-image-recommendation
	// * growthexperiments-homepage-suggestededits-tasktype-name-section-image-recommendation
	const taskTypeName = mw.message(
		'growthexperiments-homepage-suggestededits-tasktype-name-' + this.nextSuggestedTaskType,
	).parse();
	const taskTypeSpecificText = mw.message(
		'growthexperiments-help-panel-postedit-trynewtask-subheader-tasktype',
		taskTypeName,
	).parse();
	const $subHeader = $( '<div>' )
		.addClass( 'mw-ge-help-panel-postedit-subheader2' )
		.append( taskTypeSpecificText );

	const $image = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-trynewtask-image' );

	return $mainArea.append( $subHeader, $image, dismissField.$element );
};

module.exports = TryNewTaskPanel;
