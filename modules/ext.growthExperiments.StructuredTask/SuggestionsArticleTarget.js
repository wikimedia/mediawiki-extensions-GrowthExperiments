const MachineSuggestionsMode = require( './MachineSuggestionsMode.js' );

/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by SuggestionsDesktopArticleTarget and
 * SuggestionsMobileArticleTarget.
 *
 * This is used to override regular VE's ArticleTarget to customize the edit mode dropdown such
 * that the user can only switch between visual and machine suggestions modes (no source).
 *
 * @mixin mw.libs.ge.SuggestionsArticleTarget
 *
 * @constructor
 */
function SuggestionsArticleTarget() {
}

/**
 * Switch to machine suggestions mode of Visual Editor
 */
SuggestionsArticleTarget.prototype.switchToMachineSuggestions = function () {
	const url = new URL( window.location.href );
	url.searchParams.delete( 'hideMachineSuggestions' );
	location.href = url.toString();
};

/**
 * @inheritdoc
 */
SuggestionsArticleTarget.prototype.setupToolbar = function () {
	this.constructor.super.prototype.setupToolbar.apply( this, arguments );
	MachineSuggestionsMode.trackEditModeClick( this.toolbar.$element );
};

module.exports = SuggestionsArticleTarget;
