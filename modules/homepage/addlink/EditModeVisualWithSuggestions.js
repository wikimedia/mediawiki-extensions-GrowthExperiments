var LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' );

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
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'machinesuggestions_mode'
		/* eslint-enable camelcase */
	} );
}

OO.inheritClass( EditModeVisualWithSuggestions, mw.libs.ve.MWEditModeVisualTool );

EditModeVisualWithSuggestions.static.name = 'editModeVisualWithSuggestions';
EditModeVisualWithSuggestions.static.title = mw.message(
	'growthexperiments-addlink-editmode-selection-visual' ).text();
EditModeVisualWithSuggestions.static.editMode = 'visual';

/**
 * Switch to regular VE mode
 */
EditModeVisualWithSuggestions.prototype.switch = function () {
	var editMode = this.constructor.static.editMode;
	// eslint-disable-next-line camelcase
	this.logger.log( 'editmode_select', { selected_mode: editMode } );
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
	this.setActive( !!new mw.Uri().query.hideMachineSuggestions );
};

module.exports = EditModeVisualWithSuggestions;
