var LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' );

/**
 * Tool for switching to machine suggestions mode
 *
 * @class mw.libs.ge.EditModeMachineSuggestions
 * @extends mw.libs.ve.MWEditModeTool
 * @constructor
 */
function EditModeMachineSuggestions() {
	EditModeMachineSuggestions.super.apply( this, arguments );
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'machinesuggestions_mode'
		/* eslint-enable camelcase */
	} );
}

OO.inheritClass( EditModeMachineSuggestions, mw.libs.ve.MWEditModeTool );

EditModeMachineSuggestions.static.name = 'editModeMachineSuggestions';
EditModeMachineSuggestions.static.icon = 'robot';
EditModeMachineSuggestions.static.title = mw.message(
	'growthexperiments-addlink-editmode-selection-machine-suggestions' ).text();
EditModeMachineSuggestions.static.editMode = 'machineSuggestions';

/**
 * @inheritdoc
 */
EditModeMachineSuggestions.prototype.switch = function () {
	var editMode = this.constructor.static.editMode;
	// eslint-disable-next-line camelcase
	this.logger.log( 'editmode_select', { selected_mode: editMode.toLowerCase() } );
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
	this.setActive( !new mw.Uri().query.hideMachineSuggestions );
};

module.exports = EditModeMachineSuggestions;
