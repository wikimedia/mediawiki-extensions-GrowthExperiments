( function () {
	'use strict';

	function EndOfQueueWidget( config ) {
		EndOfQueueWidget.super.call( this, config );
		this.$element.append(
			$( '<div>' )
				.addClass( 'se-card-end-of-queue' )
				.append(
					$( '<h3>' ).addClass( 'se-card-title' ).text(
						mw.message( 'growthexperiments-homepage-suggestededits-no-more-results' ).text() ),
					$( '<div>' ).addClass( 'se-card-image' ),
					$( '<p>' ).addClass( 'se-card-text' )
						.text( mw.message(
							config.topicMatching ?
								'growthexperiments-homepage-suggestededits-select-other-topics-difficulty' :
								'growthexperiments-homepage-suggestededits-select-other-difficulty',
						).text() ) ) );
	}

	OO.inheritClass( EndOfQueueWidget, OO.ui.Widget );

	module.exports = EndOfQueueWidget;
}() );
