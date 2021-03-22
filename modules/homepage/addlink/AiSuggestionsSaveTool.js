/**
 * Save button for AI suggestions mode
 * The button title changes based on whether there are any accepted recommendations.
 *
 * @extends ve.ui.MWSaveTool
 *
 * @constructor
 */
function AiSuggestionsSaveTool() {
	AiSuggestionsSaveTool.super.apply( this, arguments );
	mw.config.set( 'wgEditSubmitButtonLabelPublish', true );
	mw.hook( 'growthExperiments.aiSuggestionAcceptanceChanged' ).add( function ( hasAcceptedSuggestions ) {
		this.updateSaveButtonTitle( hasAcceptedSuggestions );
	}.bind( this ) );
	this.updateSaveButtonTitle( false );
}
OO.inheritClass( AiSuggestionsSaveTool, ve.ui.MWSaveTool );

AiSuggestionsSaveTool.static.name = 'aiSuggestionsSave';
AiSuggestionsSaveTool.static.group = 'save';
AiSuggestionsSaveTool.static.commandName = 'showSave';
AiSuggestionsSaveTool.prototype.updateSaveButtonTitle = function ( hasAcceptedSuggestions ) {
	this.setTitle( hasAcceptedSuggestions ?
		ve.init.target.getSaveButtonLabel( true ) :
		mw.message( 'growthexperiments-addlink-ve-ai-mode-submit-button' ).text()
	);
};

module.exports = AiSuggestionsSaveTool;
