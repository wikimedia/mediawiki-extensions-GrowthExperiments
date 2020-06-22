'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class HomepagePage extends Page {
	get homepage() { return $( '#ca-homepage' ); }
	get firstheading() { return $( '#firstHeading' ); }

	open() {
		super.openTitle( 'Special:Homepage' );
	}
}

module.exports = new HomepagePage();
