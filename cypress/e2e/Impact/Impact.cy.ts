describe( 'Impact', () => {
	it( 'shows the user\'s edits on Special:Impact and Special:Homepage', () => {
		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password } ) => {
			cy.task( 'MwApi:Edit', {
				username: username,
				title: 'Testpage_' + username,
				text: 'Hello, this is a test message: ' + Math.random(),
				summary: 'GrowthExperiments Cypress browser test edit',
			} );
			cy.loginViaApi( username, password );

			cy.visit( 'index.php?title=Special:Impact/' + username );
			cy.get( '[data-link-id="impact-total-edits"]' ).should( 'have.text', '1' );

			cy.visit( 'index.php?title=Special:Homepage' );
			cy.get( '[data-link-id="impact-total-edits"]' ).should( 'have.text', '1' );
		} );
	} );
} );
