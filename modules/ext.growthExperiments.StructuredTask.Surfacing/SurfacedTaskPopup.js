class SurfacedTaskPopup {

	/**
	 * @param {string} targetArticleTitle
	 * @param { { title: string; description: string?; thumbnail: any } | null } extraData
	 */
	constructor( targetArticleTitle, extraData ) {
		const contentNode = this.getContentNode( extraData );

		const labelNode = document.createElement( 'b' );
		labelNode.classList.add( 'growth-surfaced-task-popup-title' );
		labelNode.textContent = mw.msg(
			'growthexperiments-surfacing-structured-tasks-highlight-popup-title',
			targetArticleTitle,
		);

		const footerNode = document.createElement( 'div' );
		footerNode.classList.add( 'growth-surfaced-task-popup-footer' );
		footerNode.insertBefore( this.getNoButton(), null );
		footerNode.insertBefore( this.getYesButton(), null );

		this.popup = new OO.ui.PopupWidget( {
			head: true,
			icon: 'link',
			// Note: these must be a jQuery objects to work around a OO.ui.PopupWidget issues
			label: $( labelNode ),
			$content: $( contentNode ),
			$footer: footerNode,
			padded: true,
			align: 'forwards',
		} );
	}

	/**
	 * @param { { title: string; description: string?; thumbnail: any } | null } extraData
	 * @return {HTMLElement}
	 * @private
	 */
	getContentNode( extraData ) {
		if ( extraData === null ) {
			const contentNode = document.createElement( 'p' );
			contentNode.textContent = mw.msg( 'growthexperiments-surfacing-structured-tasks-highlight-popup-content' );
			return contentNode;
		}

		const { title, description, thumbnail } = extraData;
		const containerNode = document.createElement( 'div' );
		containerNode.classList.add( 'growth-surfaced-task-popup-content' );

		const thumbnailNode = this.getThumbnailNode( thumbnail );
		containerNode.appendChild( thumbnailNode );

		const textContainer = document.createElement( 'div' );
		const titleNode = document.createElement( 'a' );
		titleNode.href = mw.util.getUrl( title );
		titleNode.textContent = title;
		titleNode.classList.add( 'growth-surfaced-task-popup-content-title' );
		textContainer.appendChild( titleNode );

		if ( description ) {
			const descriptionNode = document.createElement( 'p' );
			descriptionNode.classList.add( 'growth-surfaced-task-popup-content-description' );
			descriptionNode.textContent = description;
			textContainer.appendChild( descriptionNode );
		}

		containerNode.appendChild( textContainer );

		return containerNode;
	}

	/**
	 * @param {{source: string} | null} thumbnail
	 * @return {HTMLElement}
	 * @private
	 */
	getThumbnailNode( thumbnail ) {
		const thumbnailWrapper = document.createElement( 'span' );
		thumbnailWrapper.classList.add( 'cdx-thumbnail' );
		const thumbSize = '56px';
		thumbnailWrapper.style.minWidth = thumbSize;
		thumbnailWrapper.style.minHeight = thumbSize;

		if ( !thumbnail || !thumbnail.source ) {
			const placeholderWrapper = document.createElement( 'span' );
			placeholderWrapper.classList.add( 'cdx-thumbnail__placeholder' );
			placeholderWrapper.style.minWidth = thumbSize;
			placeholderWrapper.style.minHeight = thumbSize;
			const placeholderIcon = document.createElement( 'span' );
			placeholderIcon.classList.add( 'cdx-thumbnail__placeholder__icon' );
			placeholderWrapper.appendChild( placeholderIcon );
			thumbnailWrapper.appendChild( placeholderWrapper );
			return thumbnailWrapper;
		}

		const thumbnailImage = document.createElement( 'span' );
		thumbnailImage.classList.add( 'cdx-thumbnail__image' );
		thumbnailImage.style.backgroundImage = `url(${ thumbnail.source })`;
		thumbnailImage.style.minWidth = thumbSize;
		thumbnailImage.style.minHeight = thumbSize;
		thumbnailWrapper.appendChild( thumbnailImage );
		return thumbnailWrapper;
	}

	/**
	 * @return {HTMLElement}
	 */
	getElementToInsert() {
		return this.popup.$element[ 0 ];
	}

	/**
	 * @param {boolean} [show]
	 */
	toggle( show ) {
		this.popup.toggle( show );
	}

	/**
	 * @param {() => void} yesClickHandler
	 */
	addYesButtonClickHandler( yesClickHandler ) {
		this.getYesButton().addEventListener( 'click', yesClickHandler );
	}

	/**
	 * @param {() => void} noClickHandler
	 */
	addNoButtonClickHandler( noClickHandler ) {
		this.getNoButton().addEventListener( 'click', noClickHandler );
	}

	/**
	 * @param {() => void} callback
	 */
	addXButtonClickHandler( callback ) {
		// @ts-ignore This is an ugly hack to get the ooui close button, will be better with Vue
		$( this.popup.$head[ 0 ] ).find( 'a[role="button"]' ).on( 'click', callback );
	}

	/**
	 * @private
	 * @return {HTMLElement}
	 */
	getYesButton() {
		if ( !this.yesButtonNode ) {
			this.yesButtonNode = document.createElement( 'button' );
			this.yesButtonNode.classList.add( 'cdx-button' );
			const testId = document.createAttribute( 'data-testid' );
			testId.value = 'surfacing-tasks-popup-yes';
			this.yesButtonNode.attributes.setNamedItem( testId );
			this.yesButtonNode.textContent = mw.msg( 'growthexperiments-surfacing-structured-tasks-highlight-popup-yes-button-label' );
		}
		return this.yesButtonNode;
	}

	/**
	 * @private
	 * @return {HTMLElement}
	 */
	getNoButton() {
		if ( !this.noButtonNode ) {
			this.noButtonNode = document.createElement( 'button' );
			this.noButtonNode.classList.add( 'cdx-button' );
			const testId = document.createAttribute( 'data-testid' );
			testId.value = 'surfacing-tasks-popup-no';
			this.noButtonNode.attributes.setNamedItem( testId );
			this.noButtonNode.textContent = mw.msg( 'growthexperiments-surfacing-structured-tasks-highlight-popup-no-button-label' );
		}
		return this.noButtonNode;
	}
}

module.exports = SurfacedTaskPopup;
