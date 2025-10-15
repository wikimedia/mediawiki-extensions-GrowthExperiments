import Homepage from '../pageObjects/SpecialHomepage.page';
import KeepGoingModule from '../pageObjects/KeepGoing.module';

const homepage = new Homepage();
const keepGoingModule = new KeepGoingModule();

/* eslint-disable camelcase */
const negativeModelPrediction = {
	check_type: 'tone',
	details: {},
	language: 'en',
	model_name: 'edit-check',
	model_version: 'v1',
	page_title: 'Eldfjall',
	prediction: false,
	probability: 0.752,
	status_code: 200,
};
/* eslint-enable camelcase */

describe( 'Revise Tone', () => {

	beforeEach( () => {
		cy.loginAsUser( 'GE-Alice' );
		cy.setUserOptions( {
			'growthexperiments-revisetone-onboarding': '0',
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'revise-tone', 'image-recommendation' ] ),
		} );
	} );

	describe( 'On desktop', () => {

		it( 'Shows the Revise Tone Edit Check', () => {
			cy.visit( 'index.php?title=Special:Homepage' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ext-growthExperiments-ReviseToneOnboarding' ).should( 'be.visible' );
			cy.get( '.close-all-button button' ).should( 'be.visible' ).click();

			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );
			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'have.length', 1 );
		} );

		it( 'Closes the Editor when declining Edits and suggests a new task', () => {
			cy.visit( 'index.php?title=Special:Homepage' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ext-growthExperiments-ReviseToneOnboarding' ).should( 'be.visible' );
			cy.get( '.ext-growthExperiments-OnboardingDialog__header__top__button' ).click();

			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Decline' ).click();
			cy.get( '.ve-ui-editCheckActionWidget' ).find( 'input[value=appropriate]' ).click();
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'button', 'Submit' ).should( 'not.be.disabled' ).click();

			keepGoingModule.postEditDrawer.should( 'be.visible' );
			keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
		} );

		it( 'Shows the Revise Tone Edit Check and tags edits', () => {
			cy.visit( 'index.php?title=Special:Homepage' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ext-growthExperiments-ReviseToneOnboarding', { timeout: 60000 } ).should( 'be.visible' );
			cy.get( '.ext-growthExperiments-OnboardingDialog__header__top__button' ).click();

			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Revise' ).click();

			const response = { predictions: [ negativeModelPrediction ] };
			cy.intercept( 'POST', '**/models/edit-check:predict*', { body: response } ).as( 'getEditCheckPrediction' );
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

		it( 'Shows the Revise Tone Edit Check', () => {
			cy.viewport( 360, 780 );
			cy.visit( 'index.php?title=Special:Homepage/suggested-edits&mobileaction=toggle_view_mobile' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsNextButton.click();
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Eldfjall' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ext-growthExperiments-ReviseToneOnboarding' ).should( 'be.visible' );
			cy.get( '.close-all-button button' ).should( 'be.visible' ).click();

			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );

			// assert that it did not blink out of existence again:
			// eslint-disable-next-line cypress/no-unnecessary-waiting
			cy.wait( 1000 );
			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );
			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'have.length', 1 );

			// assert that it was scrolled into view:
			cy.get( '.ve-ui-editCheck-gutter-action-warning .oo-ui-image-warning' ).should( 'be.visible' );
		} );

		it( 'Closes the Editor when declining Edits and suggests a new task', () => {
			cy.visit( 'index.php?title=Special:Homepage/suggested-edits&mobileaction=toggle_view_mobile' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsNextButton.click();
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Eldfjall' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ext-growthExperiments-ReviseToneOnboarding' ).should( 'be.visible' );
			cy.get( '.ext-growthExperiments-OnboardingDialog__header__top__button' ).click();

			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Decline' ).click();
			cy.get( '.ve-ui-editCheckActionWidget' ).find( 'input[value=appropriate]' ).click();

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

		it( 'Shows the Revise Tone Edit Check and tags edits', () => {
			cy.visit( 'index.php?title=Special:Homepage/suggested-edits&mobileaction=toggle_view_mobile' );
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Kristallsee' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsNextButton.click();
			homepage.suggestedEditsCardTitle.should( 'have.text', 'Eldfjall' );
			homepage.suggestedEditsCardLink.should( 'not.have.attr', 'href', '#' );
			homepage.suggestedEditsCardLink.click();

			cy.get( '.ext-growthExperiments-ReviseToneOnboarding' ).should( 'be.visible' );
			cy.get( '.close-all-button button' ).should( 'be.visible' ).click();

			cy.get( '.ve-ui-editCheckActionWidget' ).should( 'be.visible' );
			cy.get( '.ve-ui-editCheckActionWidget' ).contains( 'a', 'Revise' ).click();

			const response = { predictions: [ negativeModelPrediction ] };
			cy.intercept( 'POST', '**/models/edit-check:predict*', { body: response } ).as( 'getEditCheckPrediction' );
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
