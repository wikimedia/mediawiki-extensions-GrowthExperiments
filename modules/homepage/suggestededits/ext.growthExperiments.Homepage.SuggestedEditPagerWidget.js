( function () {

	function SuggestedEditPagerWidget( config ) {
		SuggestedEditPagerWidget.super.call( this, config );
	}

	OO.inheritClass( SuggestedEditPagerWidget, OO.ui.Widget );

	SuggestedEditPagerWidget.prototype.setMessage = function ( currentPosition, totalCount ) {
		this.$element.html( mw.message(
			'growthexperiments-homepage-suggestededits-pager',
			mw.language.convertNumber( currentPosition ),
			mw.language.convertNumber( totalCount )
		).parse() );
	};

	module.exports = SuggestedEditPagerWidget;

}() );
