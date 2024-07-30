'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class PreferencesPage extends Page {

	get homepage() {
		return $( '#mw-input-wpgrowthexperiments-homepage-enable' );
	}

	get save() {
		return $( '#prefcontrol' );
	}

	get homepageBox() {
		return $( '[name="wpgrowthexperiments-homepage-enable"]' );
	}

	open() {
		super.openTitle( 'Special:Preferences' );
	}

	clickHomepageCheckBox() {
		this.homepageBox.scrollIntoView();
		this.homepage.waitForDisplayed();
		this.homepage.click();
		this.save.click();
	}

}

module.exports = new PreferencesPage();
