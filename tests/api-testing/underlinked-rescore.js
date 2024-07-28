'use strict';

// This isn't really an API test, just a unit test for JS code inside a PHP file, but this
// was a convenient place to put it.

const { assert } = require( 'api-testing' );

function testSubject( doc, minimumLength, smoothingFactor ) {
	const pow = Math.pow,
		max = Math.max;
	// The part below aside from the 'return' keyword is copied verbatim from
	// UnderlinkedFunctionScoreBuilder.php. The Lucene expression language used by ElasticSearch
	// isn't quite Javascript, but it's close enough for this hack to work after remapping some
	// Math functions. See:
	// https://www.elastic.co/guide/en/elasticsearch/reference/current/modules-scripting-expression.html
	// https://lucene.apache.org/core/9_4_2/expressions/org/apache/lucene/expressions/js/package-summary.html
	/* eslint-disable */
	return doc[ 'text_bytes' ] >= minimumLength
		? pow(
			max(
				0,
				1 - (
					doc[ 'outgoing_link.token_count' ].length
					/ max( 1, doc[ 'text.word_count' ] )
				)
			),
			smoothingFactor
		)
		: 0;
	/* eslint-enable */
}

// match CirrusSearch format where token_count is actually an array
function makeTokenCount( count ) {
	return Array( count ).fill( 1 );
}

const defaultMinimumLength = 300, defaultSmoothingFactor = 4;
const data = [
	{
		// below minimum length
		doc: {
			text_bytes: 200,
			'outgoing_link.token_count': makeTokenCount( 10 ),
			'text.word_count': 100,
		},
		expected: 0,
	},
	{
		// 50% link density
		doc: {
			text_bytes: 1000,
			'outgoing_link.token_count': makeTokenCount( 50 ),
			'text.word_count': 100,
		},
		expected: 0.5 ** 4,
	},
	{
		// 10% link density
		doc: {
			text_bytes: 1000,
			'outgoing_link.token_count': makeTokenCount( 10 ),
			'text.word_count': 100,
		},
		expected: 0.9 ** 4,
	},
	{
		// no links
		doc: {
			text_bytes: 1000,
			'outgoing_link.token_count': makeTokenCount( 0 ),
			'text.word_count': 100,
		},
		expected: 1,
	},
	{
		// no words
		doc: {
			text_bytes: 1000,
			'outgoing_link.token_count': makeTokenCount( 0 ),
			'text.word_count': 0,
		},
		// we wouldn't want to prioritize an empty article, but it will be excluded
		// by the minimum length filter anyway, just making sure it doesn't crash
		expected: 1,
	},
	{
		// more links than words - possible because 'outgoing_link' includes transcluded
		// content and tables while 'text' doesn't
		doc: {
			text_bytes: 1000,
			'outgoing_link.token_count': makeTokenCount( 20 ),
			'text.word_count': 10,
		},
		expected: 0,
	},
];
const dataEntries = data.entries();

describe( 'UnderlinkedFunctionScoreBuilder', () => {

	for ( const [ i, { doc, expected } ] of dataEntries ) {
		it( `should return the expected score (dataset #${ i })`, () => {
			const actual = testSubject( doc, defaultMinimumLength, defaultSmoothingFactor );
			assert.approximately( actual, expected, 0.00001 );
		} );
	}

} );
