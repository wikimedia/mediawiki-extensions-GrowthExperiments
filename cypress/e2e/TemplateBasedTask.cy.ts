import Homepage from '../pageObjects/SpecialHomepage.page';
import KeepGoingModule from '../pageObjects/KeepGoing.module';
import GuidedTour from '../pageObjects/GuidedTour.module';

const homepage = new Homepage();
const keepGoingModule = new KeepGoingModule();
const guidedTour = new GuidedTour();

describe( 'Template-based tasks', () => {
	it( 'saves change tags for unstructured task edits made via VisualEditor', () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: {
			username: string;
			password: string;
		} ) => {
			cy.loginViaApi( username, password );
		} );
		cy.setUserOptions( {
			'growthexperiments-homepage-se-filters': JSON.stringify( [ 'copyedit' ] ),
			'growthexperiments-tour-homepage-welcome': '1',
			'visualeditor-hidebetawelcome': '1',
		} );
		guidedTour.close( 'homepage_discovery' );

		cy.visit( 'index.php?title=Special:Homepage' );

		homepage.suggestedEditsCardTitle.should( 'be.visible' ).and( 'have.text', 'Classical kemençe' );
		homepage.suggestedEditsCardLink.should( 'be.visible' ).and( 'not.have.attr', 'href', '#' );
		homepage.suggestedEditsCardLink.click();

		editAndSaveCurrentPage( 'first edit', true );
		cy.assertTagsOfCurrentPageRevision( [ 'newcomer task', 'newcomer task copyedit' ] );

		editAndSaveCurrentPage( 'second edit' );
		cy.assertTagsOfCurrentPageRevision( [ 'newcomer task', 'newcomer task copyedit' ] );

		keepGoingModule.smallTaskCardLink.should( 'have.attr', 'href' );
		keepGoingModule.smallTaskCardLink.click();

		editAndSaveCurrentPage( 'third edit', true );
		cy.assertTagsOfCurrentPageRevision( [ 'newcomer task', 'newcomer task copyedit' ] );
	} );
} );

function editAndSaveCurrentPage( textToType: string, closeHelpPanel: boolean = false ): void {
	cy.get( '#ca-ve-edit' ).should( 'be.visible' ).click();

	if ( closeHelpPanel ) {
		cy.get( '.mw-ge-help-panel-cta .oo-ui-buttonElement-button' )
			.should( 'be.visible' ).click();
	}

	cy.get( '.mw-body-content.ve-ui-surface .ve-ce-surface', { timeout: 60000 } )
		.should( 'be.visible' );

	cy.get( '.mw-body-content.ve-ui-surface .ve-ce-surface [contenteditable]' ).first().type(
		textToType,
		/**
		 * Using { force: true } works around errors of the type:
		 *
		 * `cy.type()` failed because the center of this element is hidden from view
		 *
		 * They seem to happen for unknown reasons despite the center of the element being visible.
		 */
		{ force: true },
	);

	cy.get( '.mw-ge-help-panel-processdialog .oo-ui-processDialog-actions-primary .oo-ui-buttonElement-button' )
		.should( 'not.exist' );
	cy.get( '.ve-ui-toolbar-saveButton' ).should( 'be.visible' ).click();
	cy.get( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary' ).should( 'be.visible' ).click();
	keepGoingModule.postEditDrawer.should( 'be.visible' );
}
