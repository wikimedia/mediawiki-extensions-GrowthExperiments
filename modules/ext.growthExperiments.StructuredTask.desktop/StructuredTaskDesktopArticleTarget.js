var StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	StructuredTaskArticleTargetMixin = StructuredTask.StructuredTaskArticleTargetMixin,
	MachineSuggestionsMode = StructuredTask.MachineSuggestionsMode;

/**
 * DesktopArticleTarget for structured task editing flow
 *
 * @class mw.libs.ge.StructuredTaskDesktopArticleTarget
 * @extends ve.init.mw.DesktopArticleTarget
 * @mixes mw.libs.ge.StructuredTaskArticleTargetMixin
 * @constructor
 */
function StructuredTaskDesktopArticleTarget() {
	StructuredTaskDesktopArticleTarget.super.apply( this, arguments );
	StructuredTaskArticleTargetMixin.apply( this, arguments );
	this.toolbarScrollOffset = 50;
}

OO.inheritClass( StructuredTaskDesktopArticleTarget, ve.init.mw.DesktopArticleTarget );
OO.mixinClass( StructuredTaskDesktopArticleTarget, StructuredTaskArticleTargetMixin );

StructuredTaskDesktopArticleTarget.static.toolbarGroups = [];

StructuredTaskDesktopArticleTarget.static.actionGroups =
	MachineSuggestionsMode.getActionGroups( StructuredTaskDesktopArticleTarget.static.actionGroups );

/**
 * @inheritdoc
 */
StructuredTaskDesktopArticleTarget.prototype.setupToolbar = function () {
	StructuredTaskDesktopArticleTarget.super.prototype.setupToolbar.apply( this, arguments );
	if ( MachineSuggestionsMode.toolbarHasTitleElement( this.toolbar.$element ) ) {
		this.toolbar.$element.find( '.oo-ui-toolbar-bar' ).first().prepend(
			MachineSuggestionsMode.getTitleElement( { includeIcon: true } )
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
	if ( this.hasSwitched ) {
		// Custom confirmation dialog is shown so default warning should be skipped.
		return;
	}
	return StructuredTaskDesktopArticleTarget.super.prototype.onBeforeUnload.apply( this, arguments );
};

module.exports = StructuredTaskDesktopArticleTarget;
