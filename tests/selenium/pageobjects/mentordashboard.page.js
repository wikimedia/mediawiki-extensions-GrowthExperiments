'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	assert = require( 'assert' );

class MentorDashboardPage extends Page {

	get enrollButton() { return $( '.oo-ui-buttonInputWidget' ); }
	get contentText() { return $( '#mw-content-text' ); }
	get menteeOverviewModule() { return $( '.growthexperiments-mentor-dashboard-module-mentee-overview-vue' ); }

	open( query, fragment ) {
		query = query || {};
		fragment = fragment || '';
		super.openTitle( 'Special:MentorDashboard', query, fragment );
	}

	async enroll() {
		await this.enrollButton.waitForClickable();
		await this.enrollButton.click();
	}

	async awaitConfirmation() {
		await this.contentText.waitForExist();
		assert.strictEqual( await this.contentText.getText(), 'You are now enrolled as a mentor. You can continue to the mentor dashboard now.' );
	}

	async awaitForMenteeOverviewModuleExists() {
		return await this.menteeOverviewModule.waitForExist();
	}
}

module.exports = new MentorDashboardPage();
