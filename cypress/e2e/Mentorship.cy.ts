import Homepage from '../pageObjects/SpecialHomepage.page';

const homepage = new Homepage();

describe( 'Mentorship module on Special:Homepage', () => {
	before( () => {
		// Mentorship is disabled by default, so the module never renders. Enable it and let
		// everybody enroll as a mentor.
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

		// The module renders only when the mentee has a mentor. Enroll one first.
		// Make a new user for this. cy.loginAsUser caches the session, and a cached
		// mentor is enrolled already. Then this hook fails when it runs again.
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'GE-Mentor' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );
		cy.visit( 'index.php?title=Special:EnrollAsMentor' );
		cy.get( '.oo-ui-buttonInputWidget' ).should( 'be.visible' );
		cy.get( '.oo-ui-buttonInputWidget' ).click();
		cy.get( '#mw-content-text' ).contains( 'You are now enrolled as a mentor.' );
		cy.logout();
	} );

	beforeEach( () => {
		// A mentor is assigned to this user on their first Special:Homepage view.
		cy.loginAsUser( 'GE-LearnMoreMentee' );
	} );

	it( 'points "Learn more" at a URL that can be opened in a new tab', () => {
		cy.visit( 'index.php?title=Special:Homepage' );

		homepage.mentorshipLearnMoreLink
			.should( 'have.attr', 'href' )
			.and( 'contain', 'geMentorshipAbout=1' );
	} );

	it( 'opens the about dialog on a plain click, without leaving the page', () => {
		cy.visit( 'index.php?title=Special:Homepage' );
		// The module attaches the click handler. A click before that follows the href.
		// Wait until the module is ready.
		cy.window().its( 'mw.loader' )
			.invoke( 'getState', 'ext.growthExperiments.Homepage.Mentorship' )
			.should( 'equal', 'ready' );

		homepage.mentorshipLearnMoreLink.click();

		homepage.mentorshipAboutDialog.should( 'be.visible' );
		cy.location( 'search' ).should( 'not.contain', 'geMentorshipAbout' );
	} );

	it( 'opens the about dialog when the page loads with geMentorshipAbout set', () => {
		cy.visit( 'index.php?title=Special:Homepage&geMentorshipAbout=1' );

		homepage.mentorshipAboutDialog.should( 'be.visible' );
	} );
} );
