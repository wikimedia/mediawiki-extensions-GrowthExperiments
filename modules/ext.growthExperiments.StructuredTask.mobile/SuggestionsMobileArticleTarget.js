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

SuggestionsMobileArticleTarget.static.toolbarGroups = MachineSuggestionsMode.updateEditModeTool(
	SuggestionsMobileArticleTarget.static.toolbarGroups,
);

/**
 * @inheritdoc
 */
SuggestionsMobileArticleTarget.prototype.surfaceReady = function () {
	SuggestionsMobileArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	// Upon save, the page is reloaded so veaction=edit query param needs to be removed so that
	// the editor doesn't re-open.
	removeQueryParam( new URL( window.location.href ), 'veaction' );
};

module.exports = SuggestionsMobileArticleTarget;
