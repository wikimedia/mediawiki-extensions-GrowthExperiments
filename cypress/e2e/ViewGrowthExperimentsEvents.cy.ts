describe( 'Special:Log', () => {
	it( 'Shows the GrowthExperiments event Mentor assignment changes', () => {
		cy.loginAsAdmin();
		cy.visit( 'index.php?title=Special:Log' );
		cy.get( 'select[name=type]' ).select( 'GrowthExperiments log', { force: true } );
		cy.contains( 'button', 'Show' ).click();
		cy.get( '.oo-ui-labelElement-label' ).contains( 'Type of GrowthExperiments event:' );
		cy.get(
			// Two options for compatibility before and after Ie3112a4b83 (T320871)
			'#mw-input-subtype select[name=subtype], #mw-log-action-filter-growthexperiments select[name=subtype]',
		).select( 'Mentor assignment changes', { force: true } );
	} );
} );
