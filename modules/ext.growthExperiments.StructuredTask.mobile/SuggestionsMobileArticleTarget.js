const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	MachineSuggestionsMode = StructuredTask.MachineSuggestionsMode,
	SuggestionsArticleTarget = StructuredTask.SuggestionsArticleTarget,
	removeQueryParam = require( '../utils/Utils.js' ).removeQueryParam;

/**
 * MobileArticleTarget where the user can only switch between visual and machine suggestions modes
 *
 * @class mw.libs.ge.SuggestionsMobileArticleTarget
 * @extends ve.init.mw.MobileArticleTarget
 *
 * @constructor
 */
function SuggestionsMobileArticleTarget() {
	SuggestionsMobileArticleTarget.super.apply( this, arguments );
}

OO.inheritClass( SuggestionsMobileArticleTarget, ve.init.mw.MobileArticleTarget );
OO.mixinClass( SuggestionsMobileArticleTarget, SuggestionsArticleTarget );

/**
 * Add suggestionsEditMode to toolbarGroups
 * For MobileArticleTarget, the current editMode tool is added in setupToolBar and
 * the editMode tool is not exposed via this.toolbarGroups so it can't be accessed and customized
 * from SuggestionsMobileArticleTarget. Instead, a new tool group is added and the original
 * edit mode tools are un-registered (in index.js).
 */
SuggestionsMobileArticleTarget.static.toolbarGroups =
	SuggestionsMobileArticleTarget.static.toolbarGroups.push(
		MachineSuggestionsMode.getEditModeToolGroup()
	);

/**
 * @inheritdoc
 */
SuggestionsMobileArticleTarget.prototype.surfaceReady = function () {
	SuggestionsMobileArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	// Upon save, the page is reloaded so veaction=edit query param needs to be removed so that
	// the editor doesn't re-open.
	removeQueryParam( new mw.Uri(), 'veaction', true );
};

module.exports = SuggestionsMobileArticleTarget;
