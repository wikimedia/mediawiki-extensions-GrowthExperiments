const { createMwApp } = require( 'vue' );
const InterestSelectorDialog = require( './InterestSelectorDialog.vue' );

const launchButton = document.querySelector( '#growthexperiments-reading-recommendations-personalize-button' );

if ( !launchButton ) {
	throw new Error( 'Launch button not found' );
} else {
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
}
