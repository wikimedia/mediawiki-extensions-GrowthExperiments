var AddLinkArticleTarget = require( 'ext.growthExperiments.AddLink' ).AddLinkArticleTarget,
	AiSuggestionsMode = require( 'ext.growthExperiments.AddLink' ).AiSuggestionsMode;

/**
 * Mobile version of AddLinkArticleTarget
 *
 * @class mw.libs.ge.AddLinkMobileArticleTarget
 * @extends ve.init.mw.MobileArticleTarget
 * @mixes mw.libs.ge.AddLinkArticleTarget
 * @constructor
 */
function AddLinkMobileArticleTarget() {
	AddLinkMobileArticleTarget.super.apply( this, arguments );
}

OO.inheritClass( AddLinkMobileArticleTarget, ve.init.mw.MobileArticleTarget );
OO.mixinClass( AddLinkMobileArticleTarget, AddLinkArticleTarget );

AddLinkMobileArticleTarget.static.toolbarGroups =
	AiSuggestionsMode.getMobileTools( AddLinkMobileArticleTarget.static.toolbarGroups );

AddLinkMobileArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response );
	AddLinkMobileArticleTarget.super.prototype.loadSuccess.call( this, response );
};

AddLinkMobileArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkMobileArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
};

/** @inheritdoc */
AddLinkMobileArticleTarget.prototype.setupToolbar = function () {
	AddLinkMobileArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	this.toolbar.$group.addClass( 'mw-ge-ai-suggestions-title-toolgroup' );

	if ( AiSuggestionsMode.toolbarHasTitleElement( this.toolbar.$element ) ) {
		/* Replace placeholder tool with title content
		 * Using a placeholder tool instead of appending to this.$element like desktop
		 * so that the position of the existing tools can be taken into account
		 */
		this.toolbar.$group.find( '.ve-ui-toolbar-group-aiSuggestionsPlaceholder' ).html(
			AiSuggestionsMode.getTitleElement()
		);
	}
};

module.exports = AddLinkMobileArticleTarget;
