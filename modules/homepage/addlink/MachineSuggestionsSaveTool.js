/**
 * Save button for machine suggestions mode
 * The button title changes based on whether there are any accepted recommendations.
 *
 * @extends ve.ui.MWSaveTool
 *
 * @constructor
 */
function MachineSuggestionsSaveTool() {
	MachineSuggestionsSaveTool.super.apply( this, arguments );
	mw.config.set( 'wgEditSubmitButtonLabelPublish', true );
	mw.hook( 'growthExperiments.machineSuggestionAcceptanceChanged' ).add( function ( hasAcceptedSuggestions ) {
		this.updateSaveButtonTitle( hasAcceptedSuggestions );
	}.bind( this ) );
	this.updateSaveButtonTitle( false );
}
OO.inheritClass( MachineSuggestionsSaveTool, ve.ui.MWSaveTool );

MachineSuggestionsSaveTool.static.name = 'machineSuggestionsSave';
MachineSuggestionsSaveTool.static.group = 'save';
MachineSuggestionsSaveTool.static.commandName = 'showSave';
MachineSuggestionsSaveTool.prototype.updateSaveButtonTitle = function ( hasAcceptedSuggestions ) {
	this.setTitle( hasAcceptedSuggestions ?
		ve.init.target.getSaveButtonLabel( true ) :
		mw.message( 'growthexperiments-addlink-ve-machine-suggestions-mode-submit-button' ).text()
	);
};

module.exports = MachineSuggestionsSaveTool;
