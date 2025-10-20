import Homepage from '../pageObjects/SpecialHomepage.page';
import GuidedTour from '../pageObjects/GuidedTour.module';
import KeepGoingModule from '../pageObjects/KeepGoing.module';

const homepage = new Homepage();
const guidedTour = new GuidedTour();
const keepGoingModule = new KeepGoingModule();

describe( 'Revise Tone', () => {

	let usernameAlice: string;
	let passwordAlice: string;
	before( () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			usernameAlice = username;
			passwordAlice = password;
			cy.loginViaApi( username, password );
		} );
		cy.setUserOptions( {
			'growthexperiments-tour-homepage-welcome': '1',
			'growthexperiments-addimage-onboarding': '1',
			'growthexperiments-addimage-caption-onboarding': '1',
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'revise-tone', 'image-recommendation' ] ),
		} );
		guidedTour.close( 'homepage_discovery' );
	} );

	beforeEach( () => {
		cy.loginViaApi( usernameAlice, passwordAlice );
	} );

	describe( 'On desktop', () => {

		it( 'Closes the Editor when declining Edits and suggests a new task', () => {
			cy.visit( 'index.php?title=Special:Homepage' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Decline' ).click();
			cy.get( '.ve-ui-editCheckActionWidget' ).find( 'label' ).first().click();
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'button', 'Submit' ).should( 'not.be.disabled' ).click();

			keepGoingModule.postEditDrawer.should( 'be.visible' );
			keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
		} );

		it( 'Shows the Revise Tone Edit Check and tags edits', () => {
			cy.visit( 'index.php?title=Special:Homepage' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Revise' ).click();

			const peacockParagraphLength = 109;
			const deleteParagraph = '{backspace}'.repeat( peacockParagraphLength );
			cy.get( '#Tourism_and_Recreation + p' ).type( deleteParagraph + 'Kristallsee attracts approximately 25,000 visitors annually, primarily during the summer months from June to September.' );

			cy.get( '.ve-ui-toolbar-saveButton' ).should( 'be.visible' ).click();
			cy.get( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary' ).should( 'be.visible' ).click();

			keepGoingModule.postEditDrawer.should( 'be.visible' );
			keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
			cy.assertTagsOfCurrentPageRevision( [ 'newcomer task', 'newcomer task revise tone' ] );
		} );
	} );

	describe( 'On mobile', () => {

		// Flaky: T407152 - The Edit Check disappears after selecting the first item in the survey?
		it.skip( 'Closes the Editor when declining Edits and suggests a new task', () => {
			cy.visit( 'index.php?title=Special:Homepage/suggested-edits&mobileaction=toggle_view_mobile' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsNextButton.click();
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Eldfjall' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Decline' ).click();
			cy.get( '.ve-ui-editCheckActionWidget' ).find( 'label' ).first().click();

			/*
			 * In ve.ui.PositionedTargetToolbar.js:246:22 there is a timeout after which the surface
			 * is still accessed. So we need to wait for that timeout to resolve.
			 * The timeout is OO.ui.theme.getDialogTransitionDuration(), which is 250ms wikimediaui.
			 */
			// eslint-disable-next-line cypress/no-unnecessary-waiting
			cy.wait( 250 );
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'button', 'Submit' ).should( 'not.be.disabled' ).click();

			keepGoingModule.postEditDrawer.should( 'be.visible' );
			keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
		} );

		it.skip( 'Shows the Revise Tone Edit Check and tags edits', () => {
			cy.visit( 'index.php?title=Special:Homepage/suggested-edits&mobileaction=toggle_view_mobile' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsNextButton.click();
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Eldfjall' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Revise' ).click();

			const peacockParagraphLength = 103;
			const deleteParagraph = '{backspace}'.repeat( peacockParagraphLength );
			cy.get( '#Tourism_and_Hiking + p' ).type( deleteParagraph + 'Eldfjall attracts approximately 25,000 visitors annually, primarily during the summer months from June to September.' );

			cy.get( '.ve-ui-toolbar-saveButton' ).should( 'be.visible' ).click();
			cy.get( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary' ).should( 'be.visible' ).click();

			keepGoingModule.postEditDrawer.should( 'be.visible' );
			keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
			cy.assertTagsOfCurrentPageRevision( [ 'newcomer task', 'newcomer task revise tone' ] );
		} );
	} );
} );
