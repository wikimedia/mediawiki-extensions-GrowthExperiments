describe( 'Special:MentorDashboard', () => {
	it( 'allows enrolling as a mentor', () => {
		cy.loginAsAdmin();
		cy.visit( 'index.php?title=Special:CommunityConfiguration/Mentorship' );
		cy.get( '#GEMentorshipEnabled' ).should( 'be.visible' );
		cy.get( '#GEMentorshipEnabled input' ).check();
		cy.get( '#GEMentorshipAutomaticEligibility input' ).check();
		cy.get( '#GEMentorshipMinimumAge input' ).clear();
		cy.get( '#GEMentorshipMinimumAge input' ).type( '0' );
		cy.get( '#GEMentorshipMinimumEditcount input' ).clear();
		cy.get( '#GEMentorshipMinimumEditcount input' ).type( '0' );
		cy.saveCommunityConfigurationForm( 'Automated test: Make it easy to enroll as a mentor in CI' );
		cy.logout();

		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: { username: string; password: string } ) => {
			cy.loginViaApi( username, password );
		} );
		cy.visit( 'index.php?title=Special:MentorDashboard' );

		cy.get( '.oo-ui-buttonInputWidget' ).should( 'be.visible' );

		cy.get( '.oo-ui-buttonInputWidget' ).click();
		cy.get( '#mw-content-text' ).should( 'be.visible' );
		cy.get( '#mw-content-text' ).contains( 'You are now enrolled as a mentor. You can continue to the mentor dashboard now.' );
		cy.visit( 'index.php?title=Special:MentorDashboard' );
		cy.get( '.growthexperiments-mentor-dashboard-module-mentee-overview' ).should( 'be.visible' );
	} );
} );
