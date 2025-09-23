const SuggestionInteractionLogger = require( './SuggestionInteractionLogger.js' );

/**
 * Tool for switching to machine suggestions mode
 *
 * @class mw.libs.ge.EditModeMachineSuggestions
 * @extends mw.libs.ve.MWEditModeTool
 * @constructor
 */
function EditModeMachineSuggestions() {
	EditModeMachineSuggestions.super.apply( this, arguments );
}

OO.inheritClass( EditModeMachineSuggestions, mw.libs.ve.MWEditModeTool );

EditModeMachineSuggestions.static.name = 'editModeMachineSuggestions';
EditModeMachineSuggestions.static.icon = 'robot';
EditModeMachineSuggestions.static.title = mw.message(
	'growthexperiments-structuredtask-editmode-selection-machine-suggestions' ).text();
EditModeMachineSuggestions.static.editMode = 'machineSuggestions';

/**
 * @inheritdoc
 */
EditModeMachineSuggestions.prototype.switch = function () {
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
	this.toolbar.getTarget().switchToMachineSuggestions();
};

/**
 * @inheritdoc
 */
EditModeMachineSuggestions.prototype.onUpdateState = function () {
	EditModeMachineSuggestions.super.prototype.onUpdateState.apply( this, arguments );
	this.setActive( !new URL( window.location.href ).searchParams.has( 'hideMachineSuggestions' ) );
};

module.exports = EditModeMachineSuggestions;
