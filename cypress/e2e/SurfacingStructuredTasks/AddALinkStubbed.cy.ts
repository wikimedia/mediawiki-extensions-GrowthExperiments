describe( 'Surfacing Link recommendations (with api responses stubbed)', () => {
	it( 'highlights the results returned by the API', function () {

		const articleName = 'Surfacing Link recommendations cypress test page';
		cy.fixture( 'LoremIpsum.txt' ).as( 'loremIpsumText' );

		cy.task( 'MwApi:CreateUser', { usernamePrefix: 'Alice' } ).then( ( { username, password }: { username: string; password: string } ) => {

			cy.task( 'MwApi:Edit', {
				username: 'root',
				title: articleName,
				text: this.loremIpsumText,
				summary: 'GrowthExperiments Cypress browser test edit',
			} ).then( ( { pageid }: { pageid: number } ) => {
				cy.loginViaApi( username, password );

				cy.intercept( {
					method: 'GET',
					pathname: '**/api.php',
					query: {
						action: 'query',
						list: 'linkrecommendations',
						lrpageid: `${ pageid }`,
					},
				}, { fixture: 'LoremIpsumSuggestions.json' } ).as( 'getLinkRecommendations' );
			} );
		} );

		cy.viewport( 'samsung-s10' );
		cy.visit( 'index.php?title=' + articleName + '&mobileaction=toggle_view_mobile' );

		cy.wait( '@getLinkRecommendations' );

		cy.get( '.growth-surfaced-task-button' ).should( 'have.length', 3 );
		cy.get( '.growth-surfaced-task-button:first' ).click();
		cy.get( '.growth-surfaced-task-button:first' ).should( 'have.class', 'growth-surfaced-task-popup-visible' );
		// { force: true } disables scrolling into view and thus inadvertently closing the popup
		cy.get( '[data-testid="surfacing-tasks-popup-no"]:first' ).click( { force: true } );
		cy.get( '.growth-surfaced-task-button:first' ).should( 'not.have.class', 'growth-surfaced-task-popup-visible' );

		cy.get( '.growth-surfaced-task-button:first' ).click();
		cy.get( '[data-testid="surfacing-tasks-popup-yes"]:first' ).click( { force: true } );

		cy.location( 'pathname' ).should( 'equal', '/example' );
		/*
		 We are stubbing the tasks, thus the task-url is not real.
		 Assert that the redirect to what is in linkrecommendations.taskURL has happened
		*/
	} );
} );
