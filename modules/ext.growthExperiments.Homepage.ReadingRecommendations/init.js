const { createMwApp } = require( 'vue' );
const InterestSelectorDialog = require( './InterestSelectorDialog.vue' );

const launchButton = document.querySelector( '#growthexperiments-reading-recommendations-personalize-button' );

mw.loader.using( [ 'ext.testKitchen' ] ).then( async () => {
	const experiment = await mw.tk.getExperiment( 'de-1-3-1-specialhomepage-onboarding-ab-test' );

	if ( experiment ) {
		const cards = document.querySelectorAll( '.growthexperiments-reading-recommendations-list-item a' );
		cards.forEach( ( card ) => {
			card.addEventListener( 'click', () => {
				experiment.send( 'click', {
					/* eslint-disable camelcase */
					action_subtype: 'article_link',
					action_source: 'homepage',
					action_context: card.dataset.isInterestBased ? 'interest_based_recom' : 'generic_recom',
					/* eslint-enable camelcase */
				} );
			} );
		} );
	}
} );

if ( !launchButton ) {
	return;
}

launchButton.addEventListener( 'click', ( event ) => {
	event.preventDefault();

	const container = document.createElement( 'div' );
	document.body.appendChild( container );

	const app = createMwApp( InterestSelectorDialog, {
		onDismiss: () => cleanup(),
	} );
	app.mount( container );

	function cleanup() {
		app.unmount();
		container.remove();
	}
} );
