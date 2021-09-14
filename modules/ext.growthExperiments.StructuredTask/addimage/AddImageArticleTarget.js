/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by AddImageDesktopArticleTarget and
 * AddImageMobileArticleTarget.
 *
 * @mixin mw.libs.ge.AddImageArticleTarget
 * @extends ve.init.mw.ArticleTarget
 */
function AddImageArticleTarget() {
}

AddImageArticleTarget.prototype.beforeSurfaceReady = function () {
	// TODO: Set fragments on surface
};

AddImageArticleTarget.prototype.afterSurfaceReady = function () {
	this.getSurface().executeCommand( 'recommendedImage' );
};

AddImageArticleTarget.prototype.hasEdits = function () {
	// TODO: Actual implementation
	return false;
};

AddImageArticleTarget.prototype.hasReviewedSuggestions = function () {
	// TODO: Actual implementation
	return false;
};

module.exports = AddImageArticleTarget;
