'use strict';

const assert = require( 'assert' ),
	MentorDashboardPage = require( '../pageobjects/mentordashboard.page' );

describe( 'Special:MentorDashboard', () => {

	it( 'Does not trigger errors when visited', async () => {
		await MentorDashboardPage.open();
		assert.ok( true );
	} );

	it( 'Prompts to enroll as a mentor', async () => {
		await MentorDashboardPage.open();
		await MentorDashboardPage.enrollButton.waitForExist();
	} );

	it( 'Allows enrolling as a mentor', async () => {
		await MentorDashboardPage.open();
		await MentorDashboardPage.enroll();
		await MentorDashboardPage.awaitConfirmation();
		await MentorDashboardPage.open();
		await MentorDashboardPage.awaitForMenteeOverviewModuleExists();
	} );

} );
