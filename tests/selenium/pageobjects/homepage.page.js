const Page = require( 'wdio-mediawiki/Page' );

class HomepagePage extends Page {
	get homepage() { return browser.element( '#ca-homepage' ); }
	get firstheading() { return browser.element( '#firstHeading' ); }

	open() {
		super.openTitle( 'Special:Homepage' );
	}
}

module.exports = new HomepagePage();
