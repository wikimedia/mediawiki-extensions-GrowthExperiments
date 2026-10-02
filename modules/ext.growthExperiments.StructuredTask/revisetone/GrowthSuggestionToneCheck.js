// This would be happening wherever you're loading VE:

const GrowthSuggestionToneCheck = function () {
	// Parent constructor
	GrowthSuggestionToneCheck.super.apply( this, arguments );
};

OO.inheritClass( GrowthSuggestionToneCheck, mw.editcheck.ToneCheck );
GrowthSuggestionToneCheck.static.name = 'growth-suggested-tone';

GrowthSuggestionToneCheck.static.defaultConfig = ve.extendObject( {}, GrowthSuggestionToneCheck.super.static.defaultConfig, {
	showAsCheck: true,
} );

GrowthSuggestionToneCheck.static.overrides = new Map();

GrowthSuggestionToneCheck.static.setOverride = function ( node, documentModel ) {
	this.overrides.set( node, documentModel.data.getText( true, node.getRange() ) );
};

GrowthSuggestionToneCheck.static.takesFocus = true;
GrowthSuggestionToneCheck.static.checkAsync = function ( text ) {
	if ( Array.from( this.overrides.values() ).includes( text ) ) {
		return ve.createDeferred().resolve( { prediction: true, probability: 1 } ).promise();
	}
	return GrowthSuggestionToneCheck.super.static.checkAsync.call( this, text );
};

GrowthSuggestionToneCheck.prototype.getModifiedContentBranchNodes = function ( documentModel ) {
	const actuallyModifiedBranchNodes = GrowthSuggestionToneCheck.super.prototype.getModifiedContentBranchNodes.call(
		this,
		documentModel,
	);
	// An override node can also be modified. Return it once, or it gets two actions.
	return Array.from( new Set( [ ...actuallyModifiedBranchNodes, ...this.constructor.static.overrides.keys() ] ) );
};

GrowthSuggestionToneCheck.prototype.canBeShown = function ( documentModel, suggestion ) {
	// Suggestions are suppressed while this runs, so don't say this would show
	return !suggestion;
};

module.exports = GrowthSuggestionToneCheck;
