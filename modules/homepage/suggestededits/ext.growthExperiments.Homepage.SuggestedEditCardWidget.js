( function () {
	'use strict';

	/**
	 * @param {Object} data
	 * @param {string} [data.thumbnailSource] The URL to use for the task image.
	 * @param {number|null} [data.pageviews] The pageview count of the last 60 days.
	 * @param {string} [data.url] The URL to use for linking the card to an article.
	 * @param {string} [data.extract] The Raw HTML extract to use in the teaser.
	 * @param {boolean} [data.extraDataLoaded] Extra data (page views, thumbnail, extract) exists.
	 * @constructor
	 */
	function SuggestedEditCardWidget( data ) {
		SuggestedEditCardWidget.super.call( this, data );
		this.data = data;

		this.$element.append(
			$( '<div>' ).addClass( 'suggested-edits-task-card-wrapper' )
				.append(
					$( '<a>' )
						.attr( 'href', mw.util.getUrl( new mw.Title( 'Special:Homepage/newcomertask/' + data.pageId ).toString() ) )
						.addClass( 'se-card-content' )
						.append(
							this.getImageContent(),
							this.getTextContent()
						)
				)
		);
	}

	OO.inheritClass( SuggestedEditCardWidget, OO.ui.Widget );

	SuggestedEditCardWidget.prototype.getImageContent = function () {
		var $imageContent = $( '<div>' )
				.addClass( 'se-card-image' ),
			$imageForCaching = $( '<img>' );
		if ( this.data.thumbnailSource ) {
			$imageContent.addClass( 'skeleton' );
			// Download the image but don't add to the DOM.
			$imageForCaching.attr( 'src', this.data.thumbnailSource ).on( 'load', function () {
				$imageForCaching.remove();
				// The image was already downloaded, so doing this does not make another request.
				$imageContent.css( 'background', 'url(' + this.data.thumbnailSource + ') top center no-repeat' );
				// Now that the image has downloaded, remove the loading animation.
				$imageContent.removeClass( 'skeleton' );
			}.bind( this ) );
		}

		return $imageContent;
	};

	SuggestedEditCardWidget.prototype.getTextContent = function () {
		// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-class-state
		var siteDir = $( 'body' ).hasClass( 'sitedir-rtl' ) ? 'rtl' : 'ltr',
			$pageViews = $( '<div>' ).addClass( 'se-card-pageviews' ),
			$textContent = $( '<div>' )
				.addClass( 'se-card-text' )
				.attr( 'dir', siteDir )
				.append(
					$( '<h3>' ).addClass( 'se-card-title' ).text( this.data.title ),
					$( '<div>' )
						.addClass( 'se-card-extract' + ( !this.data.extraDataLoaded ? ' skeleton' : '' ) )
						.html( this.data.extract || '' )
				);
		if ( !this.data.extraDataLoaded ) {
			$pageViews.addClass( 'skeleton' );
		}
		if ( this.data.extraDataLoaded && !this.data.pageviews ) {
			// No pageview data found for this item.
			return $textContent;
		}
		$textContent.append(
			$pageViews.append(
				new OO.ui.IconWidget( { icon: 'chart' } ).toggle( this.data.extraDataLoaded ).$element,
				mw.message(
					'growthexperiments-homepage-suggestededits-pageviews',
					mw.language.convertNumber( this.data.pageviews )
				).text() ) );
		return $textContent;
	};

	module.exports = SuggestedEditCardWidget;
}() );
