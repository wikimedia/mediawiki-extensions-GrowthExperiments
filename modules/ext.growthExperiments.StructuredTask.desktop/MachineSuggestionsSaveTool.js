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
	mw.hook( 'growthExperiments.suggestionAcceptanceChange' ).add(
		this.updateSaveButtonTitle.bind( this ),
	);
	this.updateSaveButtonTitle();
}
OO.inheritClass( MachineSuggestionsSaveTool, ve.ui.MWSaveTool );

MachineSuggestionsSaveTool.static.name = 'machineSuggestionsSave';
MachineSuggestionsSaveTool.static.group = 'save';
MachineSuggestionsSaveTool.static.commandName = 'showSave';

/**
 * Update save button title based on whether the user has made any edits to the article.
 * If the user has made edits, publish button is shown; otherwise, submit button is shown.
 */
MachineSuggestionsSaveTool.prototype.updateSaveButtonTitle = function () {
	this.setTitle( ve.init.target.hasEdits() ?
		ve.init.target.getSaveButtonLabel( true ) :
		mw.message( 'growthexperiments-structuredtask-ve-machine-suggestions-mode-submit-button' ).text(),
	);
};

module.exports = MachineSuggestionsSaveTool;
