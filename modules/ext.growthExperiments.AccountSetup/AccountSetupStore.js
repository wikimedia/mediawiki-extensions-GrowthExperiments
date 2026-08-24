const { defineStore } = require( 'pinia' );
const { ref } = require( 'vue' );

module.exports = defineStore( 'accountSetup', () => {
	const api = new mw.Api();

	const step = ref( 0 );
	const modulePreset = ref( 'skipped' );

	const storedInterestArticles = mw.config.get( 'wgGEInterestArticles', [] );

	const chips = ref( storedInterestArticles.map( ( articleName ) => ( { label: articleName, value: articleName } ) ) );
	function updateChips( newChips ) {
		chips.value = newChips;
	}

	async function saveInterestArticles() {
		const pageNames = chips.value.map( ( chip ) => chip.value );
		return api.saveOption( 'growthexperiments-interest-articles-editing', JSON.stringify( pageNames ) );
	}

	async function saveInitialModulePreset() {
		return api.saveOption( 'growthexperiments-account-setup-motivation', modulePreset.value ).catch( ( error ) => {
			mw.errorLogger.logError(
				new Error( 'AccountSetup: failed to save motivation: ' + error ),
				'error.growthexperiments',
			);
		} );
	}

	function stepForward() {
		step.value++;
	}

	function stepBack() {
		step.value--;
	}

	return { step, chips, stepForward, stepBack, modulePreset, updateChips, saveInterestArticles, saveInitialModulePreset };
} );
