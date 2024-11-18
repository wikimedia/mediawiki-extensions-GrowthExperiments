class SurfacedTaskPopup {

	/**
	 * @param {string} targetArticleTitle
	 */
	constructor( targetArticleTitle ) {
		const contentNode = document.createElement( 'p' );
		contentNode.textContent = mw.msg( 'growthexperiments-surfacing-structured-tasks-highlight-popup-content' );

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
			this.yesButtonNode.classList.add( 'cdx-button', 'cdx-button--action-progressive', 'cdx-button--weight-primary' );
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
