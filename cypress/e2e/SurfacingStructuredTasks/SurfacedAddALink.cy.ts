// Override taskURL with CI's baseUrl so it can be resolved
import AddALinkVEModule from '../../pageObjects/AddALinkVE.module';
import KeepGoingModule from '../../pageObjects/KeepGoing.module';
import GuidedTour from '../../pageObjects/GuidedTour.module';

const addALinkVEModule = new AddALinkVEModule();
const keepGoingModule = new KeepGoingModule();
const guidedTour = new GuidedTour();

describe( 'Surfacing Link recommendations', () => {
	it( 'highlights the results returned by the API', () => {
		const articleName = 'JR-430 Mountaineer';

		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } )
			.then( ( { username, password }: { username: string; password: string } ) => {
				cy.loginViaApi( username, password );
			} );
		cy.setUserOptions( {
			'growthexperiments-homepage-variant': 'surfacing-structured-task',
			// eslint-disable-next-line camelcase
			homepage_mobile_discovery_notice_seen: '1',
		} );
		guidedTour.close( 'homepage_discovery' );

		cy.viewport( 'samsung-s10' );
		cy.visit( 'index.php?title=' + articleName + '&mobileaction=toggle_view_mobile' );

		cy.get( '.growth-surfaced-task-button' ).should( 'have.length', 2 );
		cy.get( '.growth-surfaced-task-button:first' ).click();
		cy.get( '.growth-surfaced-task-button:first' ).should( 'have.class', 'growth-surfaced-task-popup-visible' );
		cy.get( '.growth-surfaced-task-popup-content-title' ).should( 'have.text', '4-8-2' );
		cy.get( '.growth-surfaced-task-popup-content-title' )
			.should( 'have.attr', 'href' )
			.should( ( href ) => {
				expect( href ).to.contain( '4-8-2' );
			} );
		// { force: true } disables scrolling into view and thus inadvertently closing the popup
		cy.get( '[data-testid="surfacing-tasks-popup-no"]:first' ).click( { force: true } );
		cy.get( '.growth-surfaced-task-button:first' ).should( 'not.have.class', 'growth-surfaced-task-popup-visible' );

		cy.get( '.growth-surfaced-task-button:first' ).click();
		cy.get( '[data-testid="surfacing-tasks-popup-yes"]:first' ).click( { force: true } );

		cy.location( 'hostname' ).should( 'equal', ( new URL( Cypress.config( 'baseUrl' ) ) ).hostname );
		cy.location( 'search' ).should( 'contain', 'surfaced=1' );

		cy.get( '.structuredtask-onboarding-dialog', { timeout: 60000 } ).should( 'be.visible' );
		cy.get( '.structuredtask-onboarding-dialog-skip-button' ).click();

		addALinkVEModule.linkInspector.should( 'be.visible' );
		addALinkVEModule.linkInspectorTargetTitle.should( 'have.text', '4-8-2' );
		addALinkVEModule.yesButton.click();

		cy.get( '.ge-structuredTask-mwSaveDialog' ).should( 'be.visible' );
		cy.get( '.ge-structuredTask-mwSaveDialog .oo-ui-processDialog-actions-primary' ).click();

		keepGoingModule.smallTaskCardLink.should( 'be.visible' );

		cy.assertTagsOfCurrentPageRevision( [
			'newcomer task add link',
			'newcomer task',
			'newcomer task read view suggestion',
		] );
	} );
} );
