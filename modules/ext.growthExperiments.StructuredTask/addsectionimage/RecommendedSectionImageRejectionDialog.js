const RecommendedImageRejectionDialog = require( '../addimage/RecommendedImageRejectionDialog.js' );

/**
 * Dialog with a list of reasons for rejecting a suggestions
 *
 * @class mw.libs.ge.RecommendedSectionImageRejectionDialog
 * @extends mw.libs.ge.RecommendedImageRejectionDialog
 * @param {Object} config
 * @constructor
 */
function RecommendedSectionImageRejectionDialog( config ) {
	RecommendedSectionImageRejectionDialog.super.call( this, config );

	this.$element.addClass( 'mw-ge-recommendedLinkRejectionDialog--section-image' );
}

OO.inheritClass( RecommendedSectionImageRejectionDialog, RecommendedImageRejectionDialog );

/** @inheritDoc **/
RecommendedSectionImageRejectionDialog.static.rejectionReasons = [
	'notrelevant',
	'sectionnotappropriate',
	'noinfo',
	'offensive',
	'lowquality',
	'unfamiliar',
	'foreignlanguage',
	'other',
];

module.exports = RecommendedSectionImageRejectionDialog;
