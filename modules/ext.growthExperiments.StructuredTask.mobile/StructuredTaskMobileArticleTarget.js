var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskArticleTarget = StructuredTask.StructuredTaskArticleTarget,
	MachineSuggestionsMode = StructuredTask.MachineSuggestionsMode,
	router = require( 'mediawiki.router' );

/**
 * MobileArticleTarget for structured task editing flow
 *
 * @class mw.libs.ge.StructuredTaskMobileArticleTarget
 * @extends ve.init.mw.MobileArticleTarget
 * @mixes mw.libs.ge.StructuredTaskArticleTarget
 * @constructor
 */
function StructuredTaskMobileArticleTarget() {
	StructuredTaskMobileArticleTarget.super.apply( this, arguments );
	StructuredTaskArticleTarget.apply( this, arguments );
}

OO.inheritClass( StructuredTaskMobileArticleTarget, ve.init.mw.MobileArticleTarget );
OO.mixinClass( StructuredTaskMobileArticleTarget, StructuredTaskArticleTarget );

StructuredTaskMobileArticleTarget.static.toolbarGroups =
	MachineSuggestionsMode.getMobileTools( StructuredTaskMobileArticleTarget.static.toolbarGroups );

/**
 * @inheritdoc
 */
StructuredTaskMobileArticleTarget.prototype.setupToolbar = function () {
	StructuredTaskMobileArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
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
	MachineSuggestionsMode.trackEditModeClick( this.toolbar.$group );
};

/**
 * Update history as if the user were to navigate to edit mode from read mode
 *
 * This allows the close button to take the user to the article's read mode
 * instead of Special:Homepage and for OO.Router to show abandonededit dialog
 * which relies on hashchange event.
 *
 * @override
 */
StructuredTaskMobileArticleTarget.prototype.updateHistory = function () {
	router.navigateTo( 'read', { path: location.pathname + location.search, useReplaceState: true } );
	router.navigateTo( 'edit', {
		path: location.pathname + location.search + '#/editor/all',
		useReplaceState: false
	} );
	router.oldHash = '/editor/all';
};

module.exports = StructuredTaskMobileArticleTarget;
