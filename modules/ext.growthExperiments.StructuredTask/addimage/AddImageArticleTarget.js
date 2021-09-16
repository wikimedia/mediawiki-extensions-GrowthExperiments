var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

/**
 * @typedef mw.libs.ge.ImageRecommendationImage
 * @property {string} image Image filename in unprefixed DBkey format.
 * @property {string} source Recommendation source; one of 'wikidata', 'wikipedia', 'commons'.
 * @property {string[]} projects Lists of projects (as wiki IDs) the recommendation is from.
 *   Only used when the source is 'wikipedia'.
 * @property {Object} metadata
 * @property {string} metadata.description Image description (sanitized HTML).
 * @property {string} metadata.descriptionUrl File description page URL.
 * @property {string} metadata.fullUrl URL of full-sized image.
 * @property {string} metadata.thumbUrl URL of image thumbnail used in the toolbar dialog.
 * @property {int} metadata.originalWidth Width of original image in pixels.
 * @property {int} metadata.originalHeight Height of original image in pixels.
 */
/**
 * @typedef mw.libs.ge.ImageRecommendation
 * @property {ImageRecommendationImage[]} images Recommended images.
 * @property {string} datasetId Dataset version ID.
 */

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

/** @inheritDoc **/
AddImageArticleTarget.prototype.hasEdits = function () {
	var accepted = this.getSurface().geRecommendationAccepted;
	return accepted === true;
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.hasReviewedSuggestions = function () {
	var accepted = this.getSurface().geRecommendationAccepted;
	return accepted === true || accepted === false;
};

/** @inheritDoc **/
AddImageArticleTarget.prototype.save = function ( doc, options, isRetry ) {
	/** @var {mw.libs.ge.ImageRecommendation} taskData */
	var taskData = suggestedEditSession.taskData,
		accepted = this.getSurface().geRecommendationAccepted;

	options.plugins = 'ge-task-image-recommendation';
	// This data will be processed in HomepageHooks::onVisualEditorApiVisualEditorEditPostSaveHook
	options[ 'data-ge-task-image-recommendation' ] = JSON.stringify( {
		filename: taskData.images[ 0 ].image,
		accepted: accepted
	} );
	return this.constructor.super.prototype.save.call( this, doc, options, isRetry );
};

module.exports = AddImageArticleTarget;
