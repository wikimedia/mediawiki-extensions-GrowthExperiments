import Homepage from '../pageObjects/SpecialHomepage.page';
import KeepGoingModule from '../pageObjects/KeepGoing.module';
import AddALinkVEModule from '../pageObjects/AddALinkVE.module';

const homepage = new Homepage();
const keepGoingModule = new KeepGoingModule();
const addALinkVEModule = new AddALinkVEModule();

describe( 'Add a Link', () => {
	it( 'link inspector can be used to accept/reject links and save an article.', () => {
		const addlinkArticle = 'Douglas Adams';
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );
		cy.setUserOptions( {
			'growthexperiments-tour-homepage-discovery': '1',
			'growthexperiments-tour-homepage-welcome': '1',
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'link-recommendation' ] ),
		} );

		cy.visit( 'index.php?title=Special:Homepage' );
		homepage.suggestedEditsCardTitle.should( 'be.visible' ).and( 'have.text', addlinkArticle );
		homepage.suggestedEditsCardLink.should( 'be.visible' ).and( 'not.have.attr', 'href', '#' );
		homepage.suggestedEditsCardLink.click();

		cy.get( '.structuredtask-onboarding-dialog', { timeout: 60000 } ).should( 'be.visible' );
		cy.get( '.structuredtask-onboarding-dialog-skip-button' ).click();

		addALinkVEModule.linkInspector.should( 'be.visible' );
		addALinkVEModule.linkInspectorTargetTitle.should( 'have.text', 'Hardcover' );
		addALinkVEModule.yesButton.click();

		addALinkVEModule.linkInspectorTargetTitle.should( 'have.text', 'Houghton Mifflin Harcourt' );
		addALinkVEModule.noButton.click();
		addALinkVEModule.rejectionDialogDoneButton.click();

		cy.get( '.oo-ui-tool-name-machineSuggestionsSave' ).click();
		cy.get( '.ge-structuredTask-mwSaveDialog' ).should( 'be.visible' );
		cy.get( '.ge-structuredTask-mwSaveDialog .oo-ui-processDialog-actions-primary' ).click();

		keepGoingModule.smallTaskCardLink.should( 'be.visible' );
		keepGoingModule.smallTaskCardTitle
			.should( 'be.visible' )
			.and( 'have.text', 'The Hitchhiker\'s Guide to the Galaxy' );

		cy.assertTagsOfCurrentPageRevision( [ 'newcomer task add link', 'newcomer task' ] );
	} );
} );
