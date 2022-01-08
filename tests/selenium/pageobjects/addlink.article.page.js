'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class AddLinkArticlePage extends Page {

	get linkInspector() { return $( '.mw-ge-recommendedLinkToolbarDialog' ); }

	get yesButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-yes' ); }

	get noButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-no' ); }

	get nextButton() { return $( '.mw-ge-recommendedLinkToolbarDialog-buttons-next' ); }

	get rejectionDialogDoneButton() { return $( '.mw-ge-recommendedLinkRejectionDialog .oo-ui-messageDialog-actions' ); }

	get publishButton() { return $( '.oo-ui-tool-name-machineSuggestionsSave ' ); }

}

module.exports = new AddLinkArticlePage();
