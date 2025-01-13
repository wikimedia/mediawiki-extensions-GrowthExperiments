import './commands.ts';

import * as installCypressLogsCollector from 'cypress-terminal-report/src/installLogsCollector';

installCypressLogsCollector();

const consoleSpies = [];

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
		throw new Error(
			`Failed to subscribe to resourceloader.exception on ${ currentPage } after ${ timeSinceNavigation } seconds.` +
			'`window.document` ' + ( win.document ? 'still exists' : 'is falsy.' ),
		);
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
beforeEach( () => {
	consoleSpies.length = 0;
	Cypress.on( 'window:before:load', ( win ) => {
		consoleSpies.push( cy.spy( win.console, 'error' ) );
	} );
	Cypress.on( 'window:load', ( win: Cypress.AUTWindow & { mw: MediaWiki } ) => {
		if ( !win.mw ) {
			return;
		}

		win.setTimeout( () => subscribeWhenAvailable( win, 0 ), 0 );
	} );
} );

afterEach( () => {
	consoleSpies.forEach( ( spy ) => {
		expect( spy ).to.have.callCount( 0 );
	} );
} );
