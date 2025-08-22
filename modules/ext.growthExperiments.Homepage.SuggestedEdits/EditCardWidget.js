( function () {
	'use strict';

	/**
	 * @param {mw.libs.ge.TaskData} data Task data.
	 * @param {boolean} [data.extraDataLoaded] Extra data (page views, thumbnail, extract) exists.
	 * @constructor
	 */
	function SuggestedEditCardWidget( data ) {
		let url;
		SuggestedEditCardWidget.super.call( this, data );
		this.data = data;
		if ( data.url ) {
			// Override for developer setups
			url = data.url;
		} else if ( data.pageId ) {
			url = new mw.Title( 'Special:Homepage/newcomertask/' + data.pageId ).getUrl();
		} else if ( data.title ) {
			url = new mw.Title( data.title ).getUrl();
		}
		// Get the new-onboarding query and pass it to the url
		const uri = new mw.Uri();
		if ( uri.query[ 'new-onboarding' ] ) {
			url = new mw.Uri( url );
			url = url.extend( { 'new-onboarding': uri.query[ 'new-onboarding' ] } );
		}
		this.$element.append(
			$( '<div>' ).addClass( 'suggested-edits-task-card-wrapper' )
				.append(
					$( '<a>' )
						.attr( 'href', url )
						.addClass( 'se-card-content' )
						.append(
							this.getImageContent(),
							this.getTextContent()
						)
				)
		);

		if ( !url ) {
			// For the empty skeleton loading card on module load, don't allow
			// clicks to go anywhere.
			this.$element.on( 'click', ( e ) => {
				e.preventDefault();
			} );
		}
	}

	OO.inheritClass( SuggestedEditCardWidget, OO.ui.Widget );

	SuggestedEditCardWidget.prototype.getImageContent = function () {
		// eslint-disable-next-line mediawiki/class-doc
		const $imageContent = $( '<div>' )
				// FIXME it would be nicer to place the task type class on the card wrapper div,
				//   but with the current structure of the LESS file that wouldn't be useful.
				.addClass( 'se-card-image no-image mw-ge-tasktype-' + this.getTaskType() ),
			$imageForCaching = $( '<img>' );
		if ( this.data.thumbnailSource !== null ) {
			$imageContent.addClass( 'skeleton' );
			// Download the image but don't add to the DOM.
			$imageForCaching.attr( 'src', this.data.thumbnailSource ).on( 'load', () => {
				$imageForCaching.remove();
				// Now that the image has downloaded, remove the loading animation.
				$imageContent.removeClass( 'skeleton no-image' );
				$imageContent.addClass( 'mw-no-invert' );
				// The image was already downloaded, so doing this does not make another request.
				$imageContent.css( 'background-image', 'url("' + this.data.thumbnailSource + '")' );
			} );
		}

		return $imageContent;
	};

	SuggestedEditCardWidget.prototype.getTextContent = function () {
		// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-class-state
		const siteDir = $( 'body' ).hasClass( 'sitedir-rtl' ) ? 'rtl' : 'ltr',
			$pageViews = $( '<div>' ).addClass( 'se-card-pageviews' ),
			$textContent = $( '<div>' )
				.addClass( 'se-card-text' )
				.attr( 'dir', siteDir )
				.append(
					$( '<h3>' ).addClass( 'se-card-title' ).text( this.data.title ),
					$( '<div>' )
						.addClass( 'se-card-extract' )
						.addClass( !this.data.extraDataLoaded ? 'skeleton' : '' )
						.text( this.data.extract || '' )
				);
		if ( !this.data.extraDataLoaded ) {
			$pageViews.addClass( 'skeleton' );
		}
		if ( this.data.extraDataLoaded && !this.data.pageviews ) {
			// No pageview data found for this item.
			return $textContent;
		}
		const pageViewsMessage = this.data.pageviews ? mw.message(
			'growthexperiments-homepage-suggestededits-pageviews',
			mw.language.convertNumber( this.data.pageviews )
		).escaped() : '';
		$textContent.append(
			$pageViews.append(
				new OO.ui.IconWidget( { icon: 'chart' } ).toggle( this.data.extraDataLoaded ).$element,
				pageViewsMessage ) );
		return $textContent;
	};

	/**
	 * @return {string}
	 */
	SuggestedEditCardWidget.prototype.getTaskType = function () {
		return this.data.tasktype;
	};

	/**
	 * @return {number}
	 */
	SuggestedEditCardWidget.prototype.getPageId = function () {
		return this.data.pageId;
	};

	/**
	 * @return {string}
	 */
	SuggestedEditCardWidget.prototype.getDbKey = function () {
		return this.data.title;
	};

	module.exports = SuggestedEditCardWidget;
}() );
