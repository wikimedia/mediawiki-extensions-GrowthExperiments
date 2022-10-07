'use strict';

const assert = require( 'assert' ),
	MentorDashboardPage = require( '../pageobjects/mentordashboard.page' );

describe( 'Special:MentorDashboard', function () {

	it( 'Does not trigger errors when visited', async function () {
		await MentorDashboardPage.open();
		assert.ok( true );
	} );

	it( 'Prompts to enroll as a mentor', async function () {
		await MentorDashboardPage.open();
		await MentorDashboardPage.enrollButton.waitForExist();
	} );

	it( 'Allows enrolling as a mentor', async function () {
		await MentorDashboardPage.open();
		await MentorDashboardPage.enroll();
		await MentorDashboardPage.awaitConfirmation();
		await MentorDashboardPage.open();
		await MentorDashboardPage.awaitForMenteeOverviewModuleExists();
	} );

} );
