const Page = require( 'wdio-mediawiki/Page' );

class PreferencesPage extends Page {

	get homepage() { return browser.element( '#mw-input-wpgrowthexperiments-homepage-enable' ); }
	get save() { return browser.element( '#prefcontrol' ); }
	get homepageBox() { return browser.element( '[name="wpgrowthexperiments-homepage-enable"]' ); }

	scrollToHomepageCheckBox() { browser.execute( ( homepage ) => homepage.scrollIntoView(), browser.element( '[name="wpgrowthexperiments-homepage-enable"]' ).value ); }

	open() {
		super.openTitle( 'Special:Preferences' );
	}

	clickHomepageCheckBox() {
		this.scrollToHomepageCheckBox();
		this.homepage.waitForVisible();
		this.homepage.click();
		this.save.click();
	}

}

module.exports = new PreferencesPage();
