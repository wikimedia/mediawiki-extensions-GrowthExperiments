( function () {
	'use strict';

	function SuggestedEditPagerWidget( config ) {
		SuggestedEditPagerWidget.super.call( this, config );
	}

	OO.inheritClass( SuggestedEditPagerWidget, OO.ui.Widget );

	/**
	 * @param {number} currentPosition
	 * @param {number|undefined} totalCount Can be undefined when some of the tasks are still loading.
	 */
	SuggestedEditPagerWidget.prototype.setMessage = function ( currentPosition, totalCount ) {
		var currentPositionText, totalCountText;

		if ( totalCount !== undefined && currentPosition > totalCount ) {
			currentPositionText = mw.message( 'growthexperiments-homepage-suggestededits-pager-end' ).text();
		} else {
			currentPositionText = mw.language.convertNumber( currentPosition );
		}
		if ( totalCount === undefined ) {
			totalCountText = mw.message( 'growthexperiments-homepage-suggestededits-pager-unknown-count' ).plain();
		} else {
			totalCountText = mw.language.convertNumber( totalCount );
		}

		this.$element.html( mw.message(
			'growthexperiments-homepage-suggestededits-pager', currentPositionText, totalCountText
		).parse() );
	};

	module.exports = SuggestedEditPagerWidget;

}() );
