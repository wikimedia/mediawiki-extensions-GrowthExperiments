var AddLink = require( 'ext.growthExperiments.AddLink' ),
	AddLinkArticleTarget = AddLink.AddLinkArticleTarget,
	MachineSuggestionsMode = AddLink.MachineSuggestionsMode,
	LinkSuggestionInteractionLogger = AddLink.LinkSuggestionInteractionLogger,
	router = require( 'mediawiki.router' );

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
	this.$element.addClass( 've-init-mw-addLinkArticleTarget' );
	// eslint-disable-next-line camelcase
	this.logger = new LinkSuggestionInteractionLogger( { is_mobile: true } );
}

OO.inheritClass( AddLinkMobileArticleTarget, ve.init.mw.MobileArticleTarget );
OO.mixinClass( AddLinkMobileArticleTarget, AddLinkArticleTarget );

AddLinkMobileArticleTarget.static.toolbarGroups =
	MachineSuggestionsMode.getMobileTools( AddLinkMobileArticleTarget.static.toolbarGroups );

AddLinkMobileArticleTarget.prototype.loadSuccess = function ( response ) {
	this.beforeLoadSuccess( response );
	AddLinkMobileArticleTarget.super.prototype.loadSuccess.call( this, response );
};

AddLinkMobileArticleTarget.prototype.surfaceReady = function () {
	this.beforeSurfaceReady();
	AddLinkMobileArticleTarget.super.prototype.surfaceReady.apply( this, arguments );
	this.afterSurfaceReady();
	this.updateHistory();
};

/**
 * @inheritdoc
 */
AddLinkMobileArticleTarget.prototype.setupToolbar = function () {
	AddLinkMobileArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	this.toolbar.$group.addClass( 'mw-ge-machine-suggestions-title-toolgroup' );

	if ( MachineSuggestionsMode.toolbarHasTitleElement( this.toolbar.$element ) ) {
		/* Replace placeholder tool with title content
		 * Using a placeholder tool instead of appending to this.$element like desktop
		 * so that the position of the existing tools can be taken into account
		 */
		this.toolbar.$group.find( '.ve-ui-toolbar-group-machineSuggestionsPlaceholder' ).html(
			MachineSuggestionsMode.getTitleElement()
		);
	}
};

/**
 * Update history as if the user were to navigate to edit mode from read mode
 *
 * This allows the close button to take the user to the article's read mode
 * instead of Special:Homepage and for OO.Router to show abandonededit dialog
 * which relies on hashchange event.
 */
AddLinkMobileArticleTarget.prototype.updateHistory = function () {
	router.navigateTo( 'read', { path: location.pathname + location.search, useReplaceState: true } );
	router.navigateTo( 'edit', {
		path: location.pathname + location.search + '#/editor/all',
		useReplaceState: false
	} );
	router.oldHash = '/editor/all';
};

module.exports = AddLinkMobileArticleTarget;
