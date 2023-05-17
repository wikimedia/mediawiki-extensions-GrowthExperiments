var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

/**
 * Mixin for a ve.init.mw.ArticleTarget instance. Used by AddSectionImageDesktopArticleTarget and
 * AddSectionImageMobileArticleTarget.
 *
 * @param {mw.libs.ge.ImageSuggestionInteractionLogger} logger For testing purposes; desktop/mobile
 *  version of this class have a logger injected already.
 * @mixin mw.libs.ge.AddSectionImageArticleTarget
 * @extends ve.init.mw.ArticleTarget
 */
function AddSectionImageArticleTarget( logger ) {
	this.logger = logger;
}

/** @inheritDoc **/
AddSectionImageArticleTarget.prototype.onSaveComplete = function ( data ) {
	var sectionImageRecWarningKey = 'gesectionimagerecommendationdailytasksexceeded',
		geWarnings = data.gewarnings || [];

	geWarnings.forEach( function ( warning ) {
		if ( warning[ sectionImageRecWarningKey ] ) {
			suggestedEditSession.qualityGateConfig[ 'section-image-recommendation' ] = { dailyLimit: true };
			suggestedEditSession.save();
		}
	} );
};

module.exports = AddSectionImageArticleTarget;
