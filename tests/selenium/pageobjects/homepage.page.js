'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class HomepagePage extends Page {
	get homepage() { return $( '#ca-homepage' ); }
	get firstheading() { return $( '#firstHeading' ); }
	get suggestedEditsCard() { return $( '.suggested-edits-card' ); }
	get suggestedEditsCardTitle() { return $( '.se-card-title' ); }
	get suggestedEditsPreviousButton() { return $( '.suggested-edits-previous .oo-ui-buttonElement-button' ); }
	get suggestedEditsNextButton() { return $( '.suggested-edits-next .oo-ui-buttonElement-button' ); }

	open() {
		super.openTitle( 'Special:Homepage' );
	}
}

module.exports = new HomepagePage();
