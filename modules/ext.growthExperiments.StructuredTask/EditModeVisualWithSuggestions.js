const SuggestionInteractionLogger = require( './SuggestionInteractionLogger.js' );

/**
 * Tool for switching to regular mode of VisualEditor with machine suggestions mode.
 * This mode is the same as regular VE with the exception that the user can only switch between
 * visual and machine suggestions modes.
 *
 * @class mw.libs.ge.EditModeVisualWithSuggestions
 * @extends mw.libs.ve.MWEditModeTool
 * @constructor
 */
function EditModeVisualWithSuggestions() {
	EditModeVisualWithSuggestions.super.apply( this, arguments );
}

OO.inheritClass( EditModeVisualWithSuggestions, mw.libs.ve.MWEditModeVisualTool );

EditModeVisualWithSuggestions.static.name = 'editModeVisualWithSuggestions';
EditModeVisualWithSuggestions.static.title = mw.message(
	'growthexperiments-structuredtask-editmode-selection-visual' ).text();
EditModeVisualWithSuggestions.static.editMode = 'visual';

/**
 * Switch to regular VE mode
 */
EditModeVisualWithSuggestions.prototype.switch = function () {
	const editMode = this.constructor.static.editMode;
	SuggestionInteractionLogger.log(
		'editmode_select',
		/* eslint-disable camelcase */
		{ selected_mode: editMode.toLowerCase() },
		{ active_interface: 'machinesuggestions_mode' },
		/* eslint-enable camelcase */
	);
	if ( this.toolbar.getTarget().getSurface().getMode() === editMode ) {
		return;
	}
	this.toolbar.getTarget().maybeSwitchToVisualWithSuggestions();
};

/**
 * @inheritdoc
 */
EditModeVisualWithSuggestions.prototype.onUpdateState = function () {
	EditModeVisualWithSuggestions.super.prototype.onUpdateState.apply( this, arguments );
	this.setActive( !!new URL( window.location.href ).searchParams.has( 'hideMachineSuggestions' ) );
};

module.exports = EditModeVisualWithSuggestions;
