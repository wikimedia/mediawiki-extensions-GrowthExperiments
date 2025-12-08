describe( 'Special:Log', () => {
	it( 'Shows the GrowthExperiments event Mentor assignment changes', () => {
		cy.loginAsAdmin();
		cy.visit( 'index.php?title=Special:Log' );
		cy.get( 'select[name=type]' ).select( 'GrowthExperiments log', { force: true } );
		cy.contains( 'button', 'Show' ).click();
		cy.get( '.oo-ui-labelElement-label' ).contains( 'Type of GrowthExperiments event:' );
		cy.get( 'select[name=subtype]' ).select( 'Mentor assignment changes', { force: true } );
	} );
} );
