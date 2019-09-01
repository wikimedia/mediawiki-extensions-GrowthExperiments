const Page = require( 'wdio-mediawiki/Page' );

class PreferencesPage extends Page {
	get homepage() { return browser.element( '#mw-input-wpgrowthexperiments-homepage-enable' ); }
	get save() { return browser.element( '#prefcontrol' ); }

	open() {
		super.openTitle( 'Special:Preferences' );
	}

}

module.exports = new PreferencesPage();
