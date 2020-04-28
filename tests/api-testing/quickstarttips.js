const { assert, REST } = require( 'api-testing' );

describe( 'GET quickstarttips', () => {
	const client = new REST( 'rest.php/growthexperiments/v0/quickstarttips' );
	const taskTypeIds = [ 'copyedit', 'update', 'links', 'expand', 'references' ];
	const skins = [ 'minerva', 'vector' ];
	const editors = [ 'visualeditor', 'reading', 'wikitext', 'wikitext-2017' ];

	it( 'the copyedit response has the correct shape and parameters substituted', async () => {
		const { status: sourceStatus, body: sourceBody } = await client.get( '/vector/visualeditor/copyedit/en' );
		assert.equal( 200, sourceStatus );
		assert.equal( '<img class="growthexperiments-quickstart-tips-tip growthexperiments-quickstart-tips-tip-graphic" src="/extensions/GrowthExperiments/images/intro-typo-ltr.svg" alt=""/>',
			sourceBody[ '2' ][ 1 ] );
		assert.equal( 6, Object.keys( sourceBody ).length );
		assert.equal( true, sourceBody[ '5' ][ 0 ].includes( '"Edit"' ) );
	} );

	it( 'rtl image is loaded for rtl languages', async () => {
		const { status: sourceStatus, body: sourceBody } = await client.get( '/vector/visualeditor/copyedit/ar' );
		assert.equal( 200, sourceStatus );
		assert.equal( '<img class="growthexperiments-quickstart-tips-tip growthexperiments-quickstart-tips-tip-graphic" src="/extensions/GrowthExperiments/images/intro-typo-rtl.svg" alt=""/>',
			sourceBody[ '2' ][ 1 ] );
	} );

	it( 'loads different messages varying by skin', async () => {
		const { status: sourceStatus, body: sourceBody } = await client.get( '/minerva/visualeditor/copyedit/ar' );
		assert.equal( 200, sourceStatus );
		assert.equal( true, sourceBody[ '5' ][ 0 ].includes( 'tap the edit pencil' ) );
	} );

	skins.forEach( ( skin ) => {
		editors.forEach( ( editor ) => {
			taskTypeIds.forEach( ( taskTypeId ) => {
				it( `should get tips for ${skin} / ${editor} / ${taskTypeId} without an HTTP error`, async () => {
					const { status: sourceStatus } =
						await client.get( `/${skin}/${editor}/${taskTypeId}/en` );
					assert.equal( sourceStatus, 200 );
				} );
			} );
		} );
	} );

} );
