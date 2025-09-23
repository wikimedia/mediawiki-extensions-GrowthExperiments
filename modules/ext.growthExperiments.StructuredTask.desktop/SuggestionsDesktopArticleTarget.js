const StructuredTask = require( 'ext.growthExperiments.StructuredTask' ),
	MachineSuggestionsMode = StructuredTask.MachineSuggestionsMode,
	SuggestionsArticleTarget = StructuredTask.SuggestionsArticleTarget;

/**
 * DesktopArticleTarget where the user can only switch between visual and machine suggestions modes
 *
 * @class mw.libs.ge.SuggestionsDesktopArticleTarget
 * @extends ve.init.mw.DesktopArticleTarget
 *
 * @constructor
 */
function SuggestionsDesktopArticleTarget() {
	SuggestionsDesktopArticleTarget.super.apply( this, arguments );
}

OO.inheritClass( SuggestionsDesktopArticleTarget, ve.init.mw.DesktopArticleTarget );
OO.mixinClass( SuggestionsDesktopArticleTarget, SuggestionsArticleTarget );

SuggestionsDesktopArticleTarget.static.toolbarGroups = MachineSuggestionsMode.updateEditModeTool(
	SuggestionsDesktopArticleTarget.static.toolbarGroups,
);

module.exports = SuggestionsDesktopArticleTarget;
