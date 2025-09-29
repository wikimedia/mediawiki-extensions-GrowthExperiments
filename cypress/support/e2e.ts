import './commands.ts';
// eslint-disable-next-line @typescript-eslint/no-require-imports
require( 'cypress-terminal-report/src/installLogsCollector' )();

function subscribeWhenAvailable(
	win: Cypress.AUTWindow & { mw: MediaWiki },
	attempts: number,
): void {
	if ( !win.mw ) {
		throw new Error( '`subscribeWhenAvailable` must be called with a window that has `mw`!' );
	}

	if ( attempts > 10 ) {
		const timeSinceNavigation = win.performance.now() / 1000;
		const currentPage = win.location.toString();
		console.warn(
			`Failed to subscribe to resourceloader.exception on ${ currentPage } after ${ timeSinceNavigation } seconds.` +
			'`window.document` ' + ( win.document ? 'still exists' : 'is falsy.' ),
		);
		/**
		 * TODO: figure out why win.mw.trackSubscribe is sometimes not available
		 *       and if this is correlated to other issues.
		 */
		return;
	}

	if ( win.mw.trackSubscribe ) {
		win.mw.trackSubscribe( 'resourceloader.exception', ( topic, data ) => {
			const { exception, module, source } = data as {
				source: string;
				module: string;
				exception: Error;
			};
			console.error(
				topic,
				`${ exception.name }: '${ exception.message }' in ${ module }. (type: ${ source })` );
		} );
	} else {
		win.setTimeout( () => subscribeWhenAvailable( win, attempts + 1 ), 100 );
	}
}

function failOnConsoleError(): void {

	const consoleSpies = [];

	Cypress.on( 'window:before:load', ( win ) => {
		consoleSpies.push( cy.spy( win.console, 'error' ) );
	} );
	Cypress.on( 'window:load', ( win: Cypress.AUTWindow & { mw: MediaWiki } ) => {
		if ( !win.mw ) {
			return;
		}

		win.setTimeout( () => subscribeWhenAvailable( win, 0 ), 0 );
	} );

	beforeEach( () => {
		consoleSpies.length = 0;
	} );

	afterEach( () => {
		consoleSpies.forEach( ( spy ) => {
			expect( spy ).to.have.callCount( 0 );
		} );
	} );
}

failOnConsoleError();
