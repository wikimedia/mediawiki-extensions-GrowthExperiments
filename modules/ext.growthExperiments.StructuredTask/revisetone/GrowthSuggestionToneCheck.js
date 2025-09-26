// This would be happening wherever you're loading VE:

const GrowthSuggestionToneCheck = function () {
	// Parent constructor
	GrowthSuggestionToneCheck.super.apply( this, arguments );
};

OO.inheritClass( GrowthSuggestionToneCheck, mw.editcheck.ToneCheck );
GrowthSuggestionToneCheck.static.name = 'growth-suggested-tone';

GrowthSuggestionToneCheck.static.overrides = new Map();

GrowthSuggestionToneCheck.static.setOverride = function ( node, documentModel ) {
	this.overrides.set( node, documentModel.data.getText( true, node.getRange() ) );
};

GrowthSuggestionToneCheck.static.checkAsync = function ( text ) {
	if ( Array.from( this.overrides.values() ).includes( text ) ) {
		return ve.createDeferred().resolve( { prediction: true, probability: 1 } ).promise();
	}
	return GrowthSuggestionToneCheck.super.static.checkAsync.call( this, text );
};

GrowthSuggestionToneCheck.prototype.getModifiedContentBranchNodes = function () {
	return this.constructor.static.overrides.keys();
};

GrowthSuggestionToneCheck.prototype.canBeShown = function () {
	return true;
};

module.exports = GrowthSuggestionToneCheck;
