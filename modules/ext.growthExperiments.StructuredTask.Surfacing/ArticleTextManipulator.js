class ArticleTextManipulator {
	/**
	 * @param {HTMLElement} rootElement
	 * @param {string} text
	 * @return {?HTMLElement}
	 */
	findFirstParagraphContainingText( rootElement, text ) {
		const walker = document.createTreeWalker(
			rootElement,
			NodeFilter.SHOW_TEXT,
			{
				acceptNode: function ( node ) {
					if ( node.parentElement === null || node.parentElement.nodeName !== 'P' ) {
						return NodeFilter.FILTER_REJECT;
					}
					// REVIEW: For now, this does not look at the context. Should it?
					if ( node.textContent === null || !node.textContent.includes( text ) ) {
						return NodeFilter.FILTER_REJECT;
					}
					return NodeFilter.FILTER_ACCEPT;
				},
			},
		);

		let textNode;
		while ( textNode = walker.nextNode() ) {
			const parentElement = textNode.parentElement;
			if ( parentElement ) {
				return parentElement;
			}
		}

		return null;
	}

	/**
	 * @param {HTMLElement} paragraph
	 * @param {string} textToReplace
	 * @param {Element} element
	 */
	replaceDirectTextWithElement( paragraph, textToReplace, element ) {
		const walker = document.createTreeWalker(
			paragraph,
			NodeFilter.SHOW_TEXT,
			{
				acceptNode: function ( node ) {
					return node.parentElement === paragraph ?
						NodeFilter.FILTER_ACCEPT :
						NodeFilter.FILTER_REJECT;
				},
			},
		);

		let textNode;
		while ( textNode = walker.nextNode() ) {
			const text = textNode.textContent;
			if ( text === null ) {
				throw new Error( 'text is null but should never be' );
			}
			const index = text.indexOf( textToReplace );

			if ( index !== -1 ) {
				const before = text.slice( 0, Math.max( 0, index ) );
				const after = text.slice( Math.max( 0, index + textToReplace.length ) );

				const beforeNode = document.createTextNode( before );
				const afterNode = document.createTextNode( after );

				const parentNode = textNode.parentNode;
				if ( parentNode === null ) {
					throw new Error( 'parentNode is null but should never be' );
				}
				parentNode.insertBefore( beforeNode, textNode );
				parentNode.insertBefore( element, textNode );
				parentNode.insertBefore( afterNode, textNode );
				parentNode.removeChild( textNode );

				break;
			}
		}
	}
}

module.exports = ArticleTextManipulator;
