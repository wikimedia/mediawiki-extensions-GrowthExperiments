( function () {
	'use strict';

	/**
	 * @param {Object} data
	 * @param {string} [data.thumbnailSource] The URL to use for the task image.
	 * @param {number|null} [data.pageviews] The pageview count of the last 60 days.
	 * @param {string} [data.url] The URL to use for linking the card to an article.
	 * @param {string} [data.extract] The Raw HTML extract to use in the teaser.
	 * @constructor
	 */
	function SuggestedEditCardWidget( data ) {
		SuggestedEditCardWidget.super.call( this, data );
		this.data = data;

		this.$element.append(
			$( '<a>' )
				.attr( 'href', data.url )
				.addClass( 'se-card-content' )
				.append(
					this.getImageContent(),
					this.getTextContent()
				)
		);
	}

	OO.inheritClass( SuggestedEditCardWidget, OO.ui.Widget );

	SuggestedEditCardWidget.prototype.getImageContent = function () {
		var $imageContent = $( '<div>' ).addClass( 'se-card-image' );
		if ( this.data.thumbnailSource ) {
			$imageContent.css( 'background-image', 'url(' + this.data.thumbnailSource + ')' );
		}
		return $imageContent;
	};

	SuggestedEditCardWidget.prototype.getTextContent = function () {
		var $textContent = $( '<div>' )
			.addClass( 'se-card-text' )
			.append(
				$( '<h3>' ).addClass( 'se-card-title' ).text( this.data.title ),
				$( '<div>' ).addClass( 'se-card-extract' ).html( this.data.extract || '' )
			);
		if ( this.data.pageviews ) {
			$textContent.append(
				$( '<div>' ).addClass( 'se-card-pageviews' ).append(
					new OO.ui.IconWidget( { icon: 'chart' } ).$element,
					mw.message(
						'growthexperiments-homepage-suggestededits-pageviews',
						mw.language.convertNumber( this.data.pageviews )
					).text() ) );
		}
		return $textContent;
	};

	module.exports = SuggestedEditCardWidget;
}() );
