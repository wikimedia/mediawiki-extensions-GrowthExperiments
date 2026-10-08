'use strict';
// ResourceLoader gives this module a tree-shaken Codex at "./codex.js", and the shared
// interest selector a name at the module root. Neither file is on disk, so supply both
// here. Each path is the one the file that requires it uses: "./codex.js" for the dialog
// below, and "../vue-components/codex.js" for the selector itself.
jest.mock( './codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );
jest.mock( '../vue-components/codex.js', () => require( '@wikimedia/codex' ), { virtual: true } );
jest.mock(
	'./InterestSelector.vue',
	() => require( '../vue-components/InterestSelector.vue' ),
	{ virtual: true },
);

const EXPERIMENT_NAME = 'de-1-3-1-specialhomepage-onboarding-ab-test';

/**
 * The card list as ReadingRecommendations::getListItemHtml() renders it: the interest-based card
 * carries data-is-interest-based, the general one omits it.
 *
 * @return {string}
 */
function listHtml() {
	return `
		<ul class="growthexperiments-reading-recommendations-list">
			<li class="growthexperiments-reading-recommendations-list-item">
				<a class="cdx-card cdx-card--is-link" href="/wiki/Interest" data-is-interest-based="1">
					<span class="cdx-card__text"><span class="cdx-card__text__title">Interest</span></span>
				</a>
			</li>
			<li class="growthexperiments-reading-recommendations-list-item">
				<a class="cdx-card cdx-card--is-link" href="/wiki/General">
					<span class="cdx-card__text"><span class="cdx-card__text__title">General</span></span>
				</a>
			</li>
		</ul>
	`;
}

/**
 * Let the pending promise callbacks run.
 *
 * @return {Promise<void>}
 */
function settle() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );
}

/**
 * Run init.js against the current document, then let the Test Kitchen promise chain settle so the
 * click listeners are attached.
 *
 * @return {Promise<void>}
 */
async function loadInit() {
	jest.isolateModules( () => {
		require( './init.js' );
	} );
	await settle();
}

/**
 * @param {string} title The card's title text
 */
function clickCard( title ) {
	const card = Array.from(
		document.querySelectorAll( '.growthexperiments-reading-recommendations-list-item a' ),
	).find( ( element ) => element.textContent.trim() === title );
	card.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
}

/**
 * The cards are real links and the handler deliberately lets them navigate, which jsdom cannot do.
 * Cancelling the click anywhere in the propagation path keeps the handler under test intact and
 * the navigation out of the way.
 *
 * @param {MouseEvent} event
 */
function cancelNavigation( event ) {
	event.preventDefault();
}

describe( 'ReadingRecommendations init', () => {
	let experiment;

	beforeAll( () => {
		document.addEventListener( 'click', cancelNavigation );
	} );

	afterAll( () => {
		document.removeEventListener( 'click', cancelNavigation );
	} );

	beforeEach( () => {
		experiment = { send: jest.fn() };
		mw.loader.using.mockResolvedValue( undefined );
		mw.tk.getExperiment.mockResolvedValue( experiment );
		document.body.innerHTML = listHtml();
	} );

	afterEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';
	} );

	it( 'waits for the Test Kitchen module before asking for the experiment', async () => {
		let moduleLoaded;
		mw.loader.using.mockReturnValue( new Promise( ( resolve ) => {
			moduleLoaded = resolve;
		} ) );

		await loadInit();

		expect( mw.loader.using ).toHaveBeenCalledWith( [ 'ext.testKitchen' ] );
		expect( mw.tk.getExperiment ).not.toHaveBeenCalled();

		moduleLoaded();
		await settle();

		expect( mw.tk.getExperiment ).toHaveBeenCalledWith( EXPERIMENT_NAME );
	} );

	it( 'reports an interest-based card click as interest_based_recom', async () => {
		await loadInit();

		clickCard( 'Interest' );

		expect( experiment.send ).toHaveBeenCalledTimes( 1 );
		expect( experiment.send ).toHaveBeenCalledWith( 'click', {
			/* eslint-disable camelcase */
			action_subtype: 'article_link',
			action_source: 'homepage',
			action_context: 'interest_based_recom',
			/* eslint-enable camelcase */
		} );
	} );

	it( 'reports a general card click as generic_recom', async () => {
		await loadInit();

		clickCard( 'General' );

		expect( experiment.send ).toHaveBeenCalledTimes( 1 );
		expect( experiment.send ).toHaveBeenCalledWith( 'click', expect.objectContaining( {
			// eslint-disable-next-line camelcase
			action_context: 'generic_recom',
		} ) );
	} );

	it( 'reports every card separately', async () => {
		await loadInit();

		clickCard( 'Interest' );
		clickCard( 'General' );

		expect( experiment.send.mock.calls.map( ( [ , data ] ) => data.action_context ) )
			.toStrictEqual( [ 'interest_based_recom', 'generic_recom' ] );
	} );
} );
