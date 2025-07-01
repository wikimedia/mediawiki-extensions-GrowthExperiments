const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
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
	this.$element.addClass( 'mw-ge-structuredTaskMobileArticleTarget' );
}

OO.inheritClass( StructuredTaskMobileArticleTarget, ve.init.mw.MobileArticleTarget );
OO.mixinClass( StructuredTaskMobileArticleTarget, StructuredTaskArticleTarget );

StructuredTaskMobileArticleTarget.static.toolbarGroups = [
	// TODO: Copy this definition from upstream after Id9d587b3d73:
	// ve.init.mw.MobileArticleTarget.static.toolbarGroups.find( ( group ) => group.name === 'back' ),
	{
		name: 'back',
		include: [ 'back' ],
		excludeFromTargetWidget: true
	},
	{
		name: 'machineSuggestionsPlaceholder',
		include: [ 'machineSuggestionsPlaceholder' ]
	},
	MachineSuggestionsMode.getEditModeToolGroup(),
	{
		name: 'save',
		include: [ 'showSave' ],
		excludeFromTargetWidget: true
	}
	// TODO: Copy this definition from upstream after Id9d587b3d73:
	// ve.init.mw.MobileArticleTarget.static.toolbarGroups.find( ( group ) => group.name === 'save' )
];

/**
 * @inheritdoc
 */
StructuredTaskMobileArticleTarget.prototype.setupToolbar = function () {
	StructuredTaskMobileArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	this.toolbar.$group.addClass( 'mw-ge-machine-suggestions-title-toolgroup' );

	if ( MachineSuggestionsMode.canAddToolbarTitle( this.toolbar.$element ) ) {
		/* Replace placeholder tool with title content
		 * Using a placeholder tool instead of appending to this.$element like desktop
		 * so that the position of the existing tools can be taken into account
		 */
		const $newElement = MachineSuggestionsMode.getTitleElement(),
			placeholderTool = this.toolbar.tools.machineSuggestionsPlaceholder;
		if ( placeholderTool ) {
			placeholderTool.$element = $newElement;
		}
		this.toolbar.$group.find( '.ve-ui-toolbar-group-machineSuggestionsPlaceholder' ).html(
			$newElement
		);
	}
	MachineSuggestionsMode.trackEditModeClick( this.toolbar.$group );
};

/**
 * Update history as if the user had navigated from read mode to edit mode
 *
 * This allows the close button to take the user to the article's read mode
 * instead of Special:Homepage and for OO.RouteReferenceMapper to show abandonededit dialog
 * which relies on hashchange event.
 *
 * @override
 */
StructuredTaskMobileArticleTarget.prototype.updateHistory = function () {
	// We don't use router to navigate here to avoid firing events
	history.replaceState( null, document.title, location.pathname + location.search );
	history.pushState( null, document.title, location.pathname + location.search + '#/editor/all' );
	// Update oldHash state in router as we bypassed router
	router.oldHash = '/editor/all';
	this.maybeUpdateMobileEditorPreference();
};

/**
 * Set 'VisualEditor' as the last editor if the user has "Remember my last editor" preference set.
 * This prevents the source editor from being loaded when reloading the page.
 */
StructuredTaskMobileArticleTarget.prototype.maybeUpdateMobileEditorPreference = function () {
	if ( mw.user.options.get( 'visualeditor-tabs' ) === 'remember-last' ) {
		new mw.Api().saveOption( 'mobile-editor', 'VisualEditor' );
	}
};

/** @override **/
StructuredTaskMobileArticleTarget.prototype.teardownWithoutPrompt = function ( trackMechanism ) {
	// Close the editor overlay
	return this.constructor.super.super.prototype.tryTeardown.call(
		this, true, trackMechanism
	);
};

module.exports = StructuredTaskMobileArticleTarget;
