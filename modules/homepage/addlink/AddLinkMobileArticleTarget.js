var AddLinkArticleTarget = require( 'ext.growthExperiments.AddLink' ).AddLinkArticleTarget,
	MachineSuggestionsMode = require( 'ext.growthExperiments.AddLink' ).MachineSuggestionsMode;

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

/** @inheritdoc */
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
 * Make the close button take the user to the article's read mode
 * instead of Special:Homepage
 */
AddLinkMobileArticleTarget.prototype.updateHistory = function () {
	var uri = new mw.Uri();
	uri.fragment = '';
	window.history.replaceState( null, null, uri.toString() );
};

module.exports = AddLinkMobileArticleTarget;
