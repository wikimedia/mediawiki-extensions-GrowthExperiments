const ceRecommendedImageCaptionNode = require( './ceRecommendedImageCaptionNode.js' );

/**
 * @class mw.libs.ge.ce.AddImageLinearDeleteKeyDownHandler
 * @extends ve.ce.LinearDeleteKeyDownHandler
 * @constructor
 */
function AddImageLinearDeleteKeyDownHandler() {
	// no-op, this class is called statically
}

OO.inheritClass( AddImageLinearDeleteKeyDownHandler, ve.ce.LinearDeleteKeyDownHandler );

/** @inheritDoc **/
AddImageLinearDeleteKeyDownHandler.static.execute = function ( surface, e ) {
	const activeNode = surface.getActiveNode();
	// Don't delete caption node even when it's empty
	if ( activeNode instanceof ceRecommendedImageCaptionNode && activeNode.isEmpty() ) {
		e.preventDefault();
		return;
	}
	ve.ce.LinearDeleteKeyDownHandler.static.execute( surface, e );
};

module.exports = AddImageLinearDeleteKeyDownHandler;
