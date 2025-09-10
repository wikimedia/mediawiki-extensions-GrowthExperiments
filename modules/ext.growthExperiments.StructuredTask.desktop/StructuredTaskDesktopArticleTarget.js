const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskArticleTarget = StructuredTask.StructuredTaskArticleTarget,
	MachineSuggestionsMode = StructuredTask.MachineSuggestionsMode,
	Utils = require( '../utils/Utils.js' );

/**
 * DesktopArticleTarget for structured task editing flow
 *
 * @class mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @extends ve.init.mw.DesktopArticleTarget
 * @mixes mw.libs.ge.StructuredTaskArticleTarget
 * @constructor
 */
function StructuredTaskDesktopArticleTarget() {
	StructuredTaskDesktopArticleTarget.super.apply( this, arguments );
	StructuredTaskArticleTarget.apply( this, arguments );
	this.toolbarScrollOffset = 50;
	this.$element.addClass( 'mw-ge-structuredTaskDesktopArticleTarget' );
}

OO.inheritClass( StructuredTaskDesktopArticleTarget, ve.init.mw.DesktopArticleTarget );
OO.mixinClass( StructuredTaskDesktopArticleTarget, StructuredTaskArticleTarget );

StructuredTaskDesktopArticleTarget.static.toolbarGroups = [
	MachineSuggestionsMode.getEditModeToolGroup(),
	{
		align: 'after',
		name: 'save',
		type: 'bar',
		include: [ 'machineSuggestionsSave' ],
	},
];

/**
 * @inheritdoc
 */
StructuredTaskDesktopArticleTarget.prototype.setupToolbar = function () {
	StructuredTaskDesktopArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	if ( MachineSuggestionsMode.canAddToolbarTitle( this.toolbar.$element ) ) {
		this.toolbar.$element.find( '.oo-ui-toolbar-bar' ).first().prepend(
			MachineSuggestionsMode.getTitleElement( { includeIcon: true } ),
		);
	}
	MachineSuggestionsMode.trackEditModeClick( this.toolbar.$element );
};

/**
 * @inheritdoc
 */
StructuredTaskDesktopArticleTarget.prototype.onDocumentKeyDown = function ( e ) {
	// By default, the open toolbar dialog is closed while the editor remains open.
	// In this case, the inspector behaves as if it were part of the editing surface,
	// so the editor should be closed upon the first Esc (not the second).
	if ( e.which === OO.ui.Keys.ESCAPE ) {
		e.preventDefault();
		e.stopPropagation();
		this.tryTeardown( false, 'navigate-read' );
		return;
	}
	StructuredTaskDesktopArticleTarget.super.prototype.onDocumentKeyDown.call( this, e );
};

/**
 * @inheritdoc
 */
StructuredTaskDesktopArticleTarget.prototype.onBeforeUnload = function () {
	if ( this.hasSwitched || this.hasSaved ) {
		// Custom confirmation dialog is shown or the article has been saved
		// so default warning should be skipped.
		return;
	}
	return StructuredTaskDesktopArticleTarget.super.prototype.onBeforeUnload.apply(
		this, arguments,
	);
};

/**
 * Remove "action" query param since this opens the default editor when reloading (leaving only
 * veaction which always opens the VisualEditor)
 *
 * @override
 */
StructuredTaskDesktopArticleTarget.prototype.updateHistory = function () {
	Utils.removeQueryParam( new URL( window.location.href ), 'action' );
};

/** @override **/
StructuredTaskDesktopArticleTarget.prototype.teardownWithoutPrompt = function () {
	return this.teardown();
};

module.exports = StructuredTaskDesktopArticleTarget;
