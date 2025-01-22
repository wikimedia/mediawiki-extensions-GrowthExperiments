describe( 'Special:EditGrowthConfig', () => {
	it( 'redirects to Special:CommunityConfiguration', () => {
		cy.visit( 'index.php?title=Special:EditGrowthConfig' );

		cy.get( '#firstHeading' ).should( 'have.text', 'Community Configuration' );
	} );
} );
