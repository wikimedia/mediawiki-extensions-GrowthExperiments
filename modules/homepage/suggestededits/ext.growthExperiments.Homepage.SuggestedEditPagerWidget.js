( function () {
	'use strict';

	function SuggestedEditPagerWidget( config ) {
		SuggestedEditPagerWidget.super.call( this, config );
	}

	OO.inheritClass( SuggestedEditPagerWidget, OO.ui.Widget );

	/**
	 * @param {number} currentPosition
	 * @param {number} totalCount
	 */
	SuggestedEditPagerWidget.prototype.setMessage = function ( currentPosition, totalCount ) {
		var currentPositionText, totalCountText;

		if ( currentPosition > totalCount ) {
			currentPositionText = mw.message( 'growthexperiments-homepage-suggestededits-pager-end' ).text();
		} else {
			currentPositionText = mw.language.convertNumber( currentPosition );
		}
		totalCountText = mw.language.convertNumber( totalCount );

		this.$element.html( mw.message(
			'growthexperiments-homepage-suggestededits-pager', currentPositionText, totalCountText
		).parse() );
	};

	module.exports = SuggestedEditPagerWidget;

}() );
