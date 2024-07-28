'use strict';

const { assert, REST } = require( 'api-testing' );

/* eslint-disable mocha/no-setup-in-describe */

describe( 'GET quickstarttips', () => {
	const client = new REST( 'rest.php/growthexperiments/v0/quickstarttips' );
	const taskTypeIds = [ 'copyedit', 'update', 'expand', 'references' ];
	const skins = [ 'minerva', 'vector' ];
	const editors = [ 'visualeditor', 'wikitext', 'wikitext-2017' ];

	it( 'the copyedit response has the correct shape and parameters substituted', async () => {
		const { status: sourceStatus, body: sourceBody, error: error } = await client.get( '/vector/visualeditor/copyedit/en' );
		if ( error ) {
			console.error( error.text, error.code );
		}
		assert.equal( 200, sourceStatus );
		assert.equal( true, sourceBody[ '4' ][ 0 ].includes( '"Edit"' ) );
	} );

	it( 'loads different messages varying by skin', async () => {
		const { status: sourceStatus, body: sourceBody, error: error } = await client.get( '/minerva/visualeditor/copyedit/en' );
		if ( error ) {
			console.error( error.text, error.code );
		}
		assert.equal( 200, sourceStatus );
		assert.equal( true, sourceBody[ '4' ][ 0 ].includes( 'tap the edit pencil' ) );
	} );

	skins.forEach( ( skin ) => {
		editors.forEach( ( editor ) => {
			taskTypeIds.forEach( ( taskTypeId ) => {
				it( `should get tips for ${ skin } / ${ editor } / ${ taskTypeId } without an HTTP error`, async () => {
					const { status: sourceStatus, body: sourceBody, error: error } =
						await client.get( `/${ skin }/${ editor }/${ taskTypeId }/en` );

					function expectedNumberOfTips( innerTaskTypeId ) {
						return innerTaskTypeId === 'references' ? 7 : 6;
					}

					assert.equal(
						expectedNumberOfTips( taskTypeId ), Object.keys( sourceBody ).length
					);
					if ( error ) {
						console.error( error.text, error.code );
					}
					assert.equal( sourceStatus, 200 );
				} );
			} );
		} );
	} );

} );
