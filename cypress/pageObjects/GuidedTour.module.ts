class GuidedTour {
	public close( nameOfTheTour: string ): void {
		const tourClass = 'mw-guidedtour-tour-' + nameOfTheTour;
		cy.log( 'Closing the guided tour', nameOfTheTour );
		cy.get( `.guider.${ tourClass } .guider_close button` ).should( 'be.visible' ).click();
		cy.get( `.guider.${ tourClass }` ).should( 'not.be.visible' );
	}
}

export default GuidedTour;
