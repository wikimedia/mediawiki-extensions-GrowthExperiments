( function () {
	'use strict';

	function ErrorCardWidget( config ) {
		ErrorCardWidget.super.call( this, config );
		this.$element.append(
			$( '<div>' )
				.addClass( 'se-card-error' )
				.append(
					$( '<h3>' ).addClass( 'se-card-title' ).text(
						mw.message( 'growthexperiments-homepage-suggestededits-error-title' ).text() ),
					$( '<div>' ).addClass( 'se-card-image' ),
					$( '<p>' ).addClass( 'se-card-text' )
						.text( mw.message(
							'growthexperiments-homepage-suggestededits-error-description',
						).text() ) ) );
	}

	OO.inheritClass( ErrorCardWidget, OO.ui.Widget );

	module.exports = ErrorCardWidget;

}() );
