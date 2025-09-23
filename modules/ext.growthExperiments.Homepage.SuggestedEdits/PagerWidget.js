/**
 * Widget for showing "x of y suggestions" text in the suggested edits task feed
 *
 * The following message keys should be included:
 * - growthexperiments-homepage-suggestededits-pager
 * - growthexperiments-homepage-suggestededits-pager-end
 *
 * @param {Object} config
 * @class mw.libs.ge.SuggestedEditsPagerWidget
 * @extends OO.ui.Widget
 * @constructor
 */
function SuggestedEditPagerWidget( config ) {
	SuggestedEditPagerWidget.super.call( this, config );
}

OO.inheritClass( SuggestedEditPagerWidget, OO.ui.Widget );

/**
 * @param {number} currentPosition
 * @param {number} totalCount
 */
SuggestedEditPagerWidget.prototype.setMessage = function ( currentPosition, totalCount ) {

	let currentPositionText;
	if ( currentPosition > totalCount ) {
		currentPositionText = mw.message( 'growthexperiments-homepage-suggestededits-pager-end' ).text();
	} else {
		currentPositionText = mw.language.convertNumber( currentPosition );
	}
	const totalCountText = mw.language.convertNumber( totalCount );

	this.$element.html( mw.message(
		'growthexperiments-homepage-suggestededits-pager', currentPositionText, totalCountText,
	).parse() );
};

/**
 * Set the pager widget text to a message indicating that tasks are loading.
 */
SuggestedEditPagerWidget.prototype.setLoadingMessage = function () {
	this.$element.text( mw.message( 'growthexperiments-homepage-suggestededits-pager-loading' ).text() );
};

module.exports = SuggestedEditPagerWidget;
