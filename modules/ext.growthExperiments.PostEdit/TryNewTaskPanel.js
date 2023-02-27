var PostEditToastMessage = require( './PostEditToastMessage.js' );

/**
 * @class
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
		autoHideDuration: 5000
	} );
};

/**
 * Get the link(s) to display in the footer.
 *
 * @return {Array<jQuery>} A list of footer elements.
 */
TryNewTaskPanel.prototype.getFooterButtons = function () {
	var tryNewTaskButtonWidget = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-help-panel-postedit-trynewtask-try-button-text' ).text(),
		flags: [ 'primary', 'progressive' ],
		classes: [ 'mw-ge-help-panel-postedit-footer', 'mw-ge-help-panel-postedit-footer-trynewtask' ]
	} );
	tryNewTaskButtonWidget.connect( this, { click: 'openPostEditDialogWithNewTask' } );
	var noThanksButtonWidget = new OO.ui.ButtonWidget( {
		label: mw.message( 'growthexperiments-help-panel-postedit-trynewtask-nothanks-button-text' ).text(),
		framed: false,
		classes: [ 'mw-ge-help-panel-postedit-footer', 'mw-ge-help-panel-postedit-footer-nothanks' ]
	} );
	noThanksButtonWidget.connect( this, { click: 'openPostEditDialog' } );
	return [ new OO.ui.HorizontalLayout( {
		items: [ noThanksButtonWidget, tryNewTaskButtonWidget ],
		classes: [ 'mw-ge-help-panel-postedit-trynewtask-footer' ]
	} ).$element ];
};

/**
 * Log that the "trynewtask" button was clicked and close the panel, passing the next
 * suggested task type so that the close handler knows to use that value when opening the
 * post-edit dialog.
 */
TryNewTaskPanel.prototype.openPostEditDialogWithNewTask = function () {
	this.logLinkClick( 'trynewtask' );
	this.emit( 'close', this.nextSuggestedTaskType );
};

/**
 * Log that the "nothanks" button was clicked and close the panel, passing "null"
 * so that the close handler knows to open the post-edit dialog without any modifications
 * to the task types.
 */
TryNewTaskPanel.prototype.openPostEditDialog = function () {
	this.logLinkClick( 'nothanks' );
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
	this.helpPanelLogger.log( 'trynewtask-close', '' );
};

/**
 * Log that the panel was displayed to the user.
 * Needs to be called by the code displaying the panel.
 */
TryNewTaskPanel.prototype.logImpression = function () {
	this.helpPanelLogger.log( 'trynewtask-impression', {
		nextSuggestedTaskType: this.nextSuggestedTaskType
	} );
};

/**
 * Log that one of the footer buttons was clicked.
 * This is handled automatically by the class.
 *
 * @param {string} linkName Symbolic link name ('nothanks' or 'trynewtask').
 */
TryNewTaskPanel.prototype.logLinkClick = function ( linkName ) {
	this.helpPanelLogger.log( 'trynewtask-link-click', linkName );
};

/**
 * Get the main area of the panel (the card with a subheader).
 *
 * @return {jQuery|null} The main area, a jQuery object wrapping the card element.
 *   Null if the panel should not have a main area (as no task should be displayed).
 */
TryNewTaskPanel.prototype.getMainArea = function () {
	var $mainArea = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-main' );
	var optOutButton = new OO.ui.CheckboxInputWidget( {
		selected: false,
		value: 'optOutForTaskType'
	} );
	optOutButton.on( 'change', function ( isSelected ) {
		if ( isSelected ) {
			this.tryNewTaskOptOuts.push( this.activeTaskType );
		} else {
			this.tryNewTaskOptOuts = this.tryNewTaskOptOuts.filter( function ( taskType ) {
				return taskType !== this.activeTaskType;
			}.bind( this ) );
		}
		new mw.Api().saveOption(
			'growthexperiments-levelingup-tasktype-prompt-optouts',
			JSON.stringify( this.tryNewTaskOptOuts )
		);
	}.bind( this ) );

	var dismissField = new OO.ui.FieldLayout( optOutButton, {
		label: mw.message(
			'growthexperiments-help-panel-postedit-trynewtask-dontshowagain-checkbox'
		).text(),
		align: 'inline',
		classes: [ 'mw-ge-tryNewTaskPanel-dismiss-field' ]
	} );

	// The following messages are used here:
	// * growthexperiments-homepage-suggestededits-tasktype-name-references
	// * growthexperiments-homepage-suggestededits-tasktype-name-copyedit
	// * growthexperiments-homepage-suggestededits-tasktype-name-expand
	// * growthexperiments-homepage-suggestededits-tasktype-name-update
	// * growthexperiments-homepage-suggestededits-tasktype-name-links
	// * growthexperiments-homepage-suggestededits-tasktype-name-link-recommendation
	// * growthexperiments-homepage-suggestededits-tasktype-name-image-recommendation
	var taskTypeName = mw.message(
		'growthexperiments-homepage-suggestededits-tasktype-name-' + this.nextSuggestedTaskType
	).parse();
	var taskTypeSpecificText = mw.message(
		'growthexperiments-help-panel-postedit-trynewtask-subheader-tasktype',
		taskTypeName
	).parse();
	var $subHeader = $( '<div>' )
		.addClass( 'mw-ge-help-panel-postedit-subheader2' )
		.append( taskTypeSpecificText );

	var $image = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-trynewtask-image' );

	return $mainArea.append( $subHeader, $image, dismissField.$element );
};

module.exports = TryNewTaskPanel;
