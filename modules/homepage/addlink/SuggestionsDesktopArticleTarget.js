var AddLink = require( 'ext.growthExperiments.AddLink' ),
	MachineSuggestionsMode = AddLink.MachineSuggestionsMode,
	SuggestionsArticleTarget = AddLink.SuggestionsArticleTarget;

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

SuggestionsDesktopArticleTarget.static.actionGroups = MachineSuggestionsMode.updateEditModeTool(
	SuggestionsDesktopArticleTarget.static.actionGroups
);

module.exports = SuggestionsDesktopArticleTarget;
