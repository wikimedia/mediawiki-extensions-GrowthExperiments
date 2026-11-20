const router = require( 'mediawiki.router' );

/**
 * Update history as if the user had navigated from read mode to edit mode
 *
 * This allows the close button to take the user to the article's read mode
 * instead of the prior page and for OO.RouteReferenceMapper to show abandonededit dialog
 * which relies on hashchange event.
 */
function updateMobileEditorHistory() {
	// We don't use router to navigate here to avoid firing events
	history.replaceState( null, document.title, location.pathname + location.search );
	history.pushState( null, document.title, location.pathname + location.search + '#/editor/all' );
	// Update oldHash state in router as we bypassed router
	router.oldHash = '/editor/all';
}

module.exports = updateMobileEditorHistory;
