( function () {
	'use strict';

	function NoResultsWidget( config ) {
		NoResultsWidget.super.call( this, config );
		this.$element.append(
			$( '<div>' )
				.addClass( 'se-card-no-results' )
				.append(
					$( '<h3>' ).addClass( 'se-card-title' ).text(
						mw.message( 'growthexperiments-homepage-suggestededits-no-results' ).text() ),
					$( '<div>' ).addClass( 'se-card-image' ),
					$( '<p>' ).addClass( 'se-card-text' )
						.text( mw.message(
							config.topicMatching ?
								'growthexperiments-homepage-suggestededits-select-other-topics-difficulty' :
								'growthexperiments-homepage-suggestededits-select-other-difficulty'
						).text() ) ) );
	}

	OO.inheritClass( NoResultsWidget, OO.ui.Widget );

	module.exports = NoResultsWidget;
}() );
