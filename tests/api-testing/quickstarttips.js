const { assert, REST } = require( 'api-testing' );

describe( 'GET quickstarttips', () => {
	const client = new REST( 'rest.php/growthexperiments/v0/quickstarttips' );
	const taskTypeIds = [ 'copyedit', 'update', 'links', 'expand', 'references' ];
	const skins = [ 'minerva', 'vector' ];
	const editors = [ 'visualeditor', 'wikitext', 'wikitext-2017' ];

	it( 'the copyedit response has the correct shape and parameters substituted', async () => {
		const { status: sourceStatus, body: sourceBody, error: error } = await client.get( '/vector/visualeditor/copyedit/en' );
		if ( error ) {
			// eslint-disable-next-line no-console
			console.error( error.text, error.code );
		}
		assert.equal( 200, sourceStatus );
		assert.equal( '<img class="growthexperiments-quickstart-tips-tip growthexperiments-quickstart-tips-tip-graphic" src="/extensions/GrowthExperiments/images/intro-typo-ltr.svg" alt=""/>',
			sourceBody[ '1' ][ 1 ] );
		assert.equal( true, sourceBody[ '4' ][ 0 ].includes( '"Edit"' ) );
	} );

	it( 'rtl image is loaded for rtl languages', async () => {
		const { status: sourceStatus, body: sourceBody, error: error } = await client.get( '/vector/visualeditor/copyedit/ar' );
		if ( error ) {
			// eslint-disable-next-line no-console
			console.error( error.text, error.code );
		}
		assert.equal( 200, sourceStatus );
		assert.equal( '<img class="growthexperiments-quickstart-tips-tip growthexperiments-quickstart-tips-tip-graphic" src="/extensions/GrowthExperiments/images/intro-typo-rtl.svg" alt=""/>',
			sourceBody[ '1' ][ 1 ] );
	} );

	it( 'loads different messages varying by skin', async () => {
		const { status: sourceStatus, body: sourceBody, error: error } = await client.get( '/minerva/visualeditor/copyedit/en' );
		if ( error ) {
			// eslint-disable-next-line no-console
			console.error( error.text, error.code );
		}
		assert.equal( 200, sourceStatus );
		assert.equal( true, sourceBody[ '4' ][ 0 ].includes( 'tap the edit pencil' ) );
	} );

	skins.forEach( ( skin ) => {
		editors.forEach( ( editor ) => {
			taskTypeIds.forEach( ( taskTypeId ) => {
				it( `should get tips for ${skin} / ${editor} / ${taskTypeId} without an HTTP error`, async () => {
					const { status: sourceStatus, body: sourceBody, error: error } =
						await client.get( `/${skin}/${editor}/${taskTypeId}/en` );

					function expectedNumberOfTips( taskTypeId ) {
						return taskTypeId === 'references' ? 7 : 6;
					}

					assert.equal( expectedNumberOfTips( taskTypeId ), Object.keys( sourceBody ).length );
					if ( error ) {
						// eslint-disable-next-line no-console
						console.error( error.text, error.code );
					}
					assert.equal( sourceStatus, 200 );
				} );
			} );
		} );
	} );

} );
