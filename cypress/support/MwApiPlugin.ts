/**
 * Helper methods for generic MediaWiki API functionality separate from the Cypress browser context
 *
 * This file is intended to be extracted into a separate npm package,
 * so that it can be used across extensions.
 */

// needed for api-testing library, see api-testing/lib/config.js
process.env.REST_BASE_URL = process.env.MW_SERVER + process.env.MW_SCRIPT_PATH + '/';

import { clientFactory, utils } from 'api-testing';

// TODO: replace the `any` once the api-testing library type definitions available
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const state: { users: Record<string, any> } = {
	users: {},
};

function debugLog( ...args: unknown[] ): void {
	if ( !process.env.MW_DEBUG ) {
		return;
	}
	console.log( ...args );
}

function mwApiCommands( cypressConfig: Cypress.PluginConfigOptions ): {
	'MwApi:CreateUser': ( param1: { usernamePrefix: string } ) => Promise<{ username: string; password: string }>;
	'MwApi:Edit': ( param1: { username: string; title: string; text: string; summary: string } ) => Promise<null>;
} {
	// TODO: replace the `any` once the api-testing library type definitions available
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	async function root(): Promise<any> {
		if ( state.users.root ) {
			return state.users.root;
		}

		debugLog( 'Getting new root user client' );
		const rootClient = clientFactory.getActionClient( null );
		await rootClient.login(
			cypressConfig.env.mediawikiAdminUsername,
			cypressConfig.env.mediawikiAdminPassword,
		);
		await rootClient.loadTokens( [ 'createaccount', 'userrights', 'csrf' ] );

		const rightsToken = await rootClient.token( 'userrights' );
		if ( rightsToken === '+\\' ) {
			throw new Error( 'Failed to get the root user tokens.' );
		}

		state.users.root = rootClient;
		return rootClient;
	}

	return {
		async 'MwApi:CreateUser'( { usernamePrefix } ) {
			const rootUser = await root();
			const username = utils.title( usernamePrefix + '-' );
			const password = utils.uniq();

			debugLog( 'Creating account for', username );
			await rootUser.createAccount( { username: username, password: password } );

			const userClient = clientFactory.getActionClient( null );
			await userClient.login(
				username,
				password,
			);

			state.users[ username ] = userClient;

			return { username, password };
		},

		async 'MwApi:Edit'( { username, title, text, summary } ) {
			const userClient = state.users[ username ];
			if ( !userClient ) {
				throw new Error( 'User not found.' );
			}
			const token = await userClient.token( 'csrf' );

			const editResult = await userClient.action( 'edit', { title, text, summary, token }, true );

			if ( editResult.edit.result !== 'Success' ) {
				console.error( 'Edit failed', editResult );
				throw new Error( 'edit failed: ' + editResult.edit.result );
			}

			return editResult.edit;
		},
	};
}

export { mwApiCommands };
