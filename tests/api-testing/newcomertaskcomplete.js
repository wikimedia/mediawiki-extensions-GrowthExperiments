'use strict';

const { assert, REST } = require( 'api-testing' );

describe( 'POST /growthexperiments/v0/newcomertask/complete', () => {

	const client = new REST( 'rest.php/growthexperiments/v0/' );

	it( 'the endpoint is not accessible to anonymous users', async () => {
		const { body: sourceBody } = await client.post( 'newcomertask/complete?taskTypeId=foo&revId=123' );
		assert.strictEqual( sourceBody.httpCode, 403 );
		assert.strictEqual( sourceBody.message, 'You must be logged-in' );
	} );

	it( 'revId param is required', async () => {
		const { body: sourceBody } = await client.post( 'newcomertask/complete?taskTypeId=foo' );
		assert.strictEqual( sourceBody.httpCode, 400 );
		assert.strictEqual( sourceBody.name, 'revId' );
		assert.strictEqual( sourceBody.failureCode, 'missingparam' );
	} );

	it( 'taskTypeId param is required', async () => {
		const { body: sourceBody } = await client.post( 'newcomertask/complete?revId=foo' );
		assert.strictEqual( sourceBody.httpCode, 400 );
		assert.strictEqual( sourceBody.name, 'taskTypeId' );
		assert.strictEqual( sourceBody.failureCode, 'missingparam' );
	} );

	it( 'revId must be an integer', async () => {
		const { body: sourceBody } = await client.post( 'newcomertask/complete?revId=foo&taskTypeId=bar' );
		assert.strictEqual( sourceBody.httpCode, 400 );
		assert.strictEqual( sourceBody.name, 'revId' );
		assert.strictEqual( sourceBody.failureCode, 'badinteger' );
	} );

} );
