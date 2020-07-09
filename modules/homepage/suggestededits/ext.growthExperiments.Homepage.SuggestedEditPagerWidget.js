( function () {
	'use strict';

	function SuggestedEditPagerWidget( config ) {
		SuggestedEditPagerWidget.super.call( this, config );
	}

	OO.inheritClass( SuggestedEditPagerWidget, OO.ui.Widget );

	SuggestedEditPagerWidget.prototype.setMessage = function ( currentPosition, totalCount ) {
		this.$element.html( mw.message(
			'growthexperiments-homepage-suggestededits-pager',
			currentPosition <= totalCount ?
				mw.language.convertNumber( currentPosition ) :
				mw.message( 'growthexperiments-homepage-suggestededits-pager-end' ).parse(),
			mw.language.convertNumber( totalCount )
		).parse() );
	};

	module.exports = SuggestedEditPagerWidget;

}() );
