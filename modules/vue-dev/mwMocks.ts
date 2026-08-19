// Mocks for the parts of the `mw` runtime that the demoed components use.
// TODO find ways to reuse this between app and tests and GE modules
export const mwLanguageMock = {
	convertNumber: ( x: number ): string => String( x ),
	getFallbackLanguageChain: (): string[] => ( [ 'en' ] ),
};

export const mwUserMock = {
	getName: (): string => 'Mock user',
};

export const mwConfigMock = {
	get: ( key: string ): unknown => {
		const values: Record<string, unknown> = {
			wgSiteName: 'Dev Wiki',
			GEInterestArticles: [],
		};
		if ( !( key in values ) ) {
			// eslint-disable-next-line no-console
			console.debug( `mwConfigMock.get( ${ key } ): no mock value defined, returning null` );
			return null;
		}
		return values[ key ];
	},
};

/**
 * Read-only API client backed by the production English Wikipedia Action API,
 * so components doing real queries (e.g. InterestSelector search) work in dev.
 * Write operations are stubbed out and only logged.
 */
export const mwForeignApiMock = {
	get: async ( params: Record<string, string|number> ): Promise<object> => {
		const url = new URL( 'https://en.wikipedia.org/w/api.php' );
		url.searchParams.set( 'format', 'json' );
		url.searchParams.set( 'formatversion', '2' );
		url.searchParams.set( 'origin', '*' );
		for ( const [ key, value ] of Object.entries( params ) ) {
			url.searchParams.set( key, String( value ) );
		}
		const response = await fetch( url.toString() );
		return response.json() as Promise<object>;
	},
	saveOption: ( optionName: string, value: unknown ): Promise<void> => {
		// eslint-disable-next-line no-console
		console.debug( `mwForeignApiMock.saveOption( ${ optionName }, ${ JSON.stringify( value ) } )` );
		return Promise.resolve();
	},
};

export const mwApiMock = function MwApiMock(): object {
	return {
		saveOption( optionName: string, value: never ): Promise<void> {
			// eslint-disable-next-line no-console
			console.debug( `MwApiMock.saveOption( ${ optionName }, ${ value } )` );
			return Promise.resolve();
		},
	};
};

export const mwHookMock = function mwHook( hookName: string ): object {
	return {
		fire(): void {
			// eslint-disable-next-line no-console
			console.debug( `mwHook.fire( ${ hookName } )` );
		},
	};
};

export const mwTrackMock = function mwTrack( topic: string, value: number, extraData: object ): void {
	// eslint-disable-next-line no-console
	console.debug( `mwTrack( ${ topic }, ${ value }, ${ JSON.stringify( extraData ) } )` );
};

/**
 * Install a global `window.mw` stub for components that still access the `mw`
 * global directly instead of using injected endpoints (e.g. AccountSetup).
 * Prefer injecting `mw.<something>` endpoints in the components; this stub is
 * a stopgap until they are all migrated.
 */
export const installMwGlobalStub = (): void => {
	( window as unknown as { mw: unknown } ).mw = {
		config: mwConfigMock,
		user: mwUserMock,
		language: mwLanguageMock,
		Api: function MwApiGlobalStub(): object {
			return mwForeignApiMock;
		},
		ForeignApi: function MwForeignApiGlobalStub(): object {
			return mwForeignApiMock;
		},
		hook: mwHookMock,
		track: mwTrackMock,
	};
};
