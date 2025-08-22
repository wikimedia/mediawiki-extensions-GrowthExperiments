'use strict';

const { compareTwoStrings, findBestMatch } = require( './SimpleLevenshtein.js' );

describe( 'SimpleLevenshtein', () => {
	describe( 'compareTwoStrings', () => {
		it( 'returns 0 for identical strings', () => {
			expect( compareTwoStrings( 'test', 'test' ) ).toBe( 0 );
		} );

		it( 'expects the length of the string if the other string is empty', () => {
			expect( compareTwoStrings( 'test', '' ) ).toBe( 4 );
			expect( compareTwoStrings( '', 'test' ) ).toBe( 4 );
		} );

		it( 'calculates the distance between two different strings', () => {
			expect( compareTwoStrings( 'kitten', 'sitting' ) ).toBe( 3 );
		} );
	} );

	describe( 'findBestMatch', () => {
		it( 'picks the closest out of a couple of options', () => {
			const { ratings, bestMatch, bestMatchIndex } = findBestMatch(
				'Sunday',
				[
					'Wednesday',
					'Thursday',
					'Saturday',
				],
			);

			expect( ratings ).toEqual( [
				{ target: 'Wednesday', distance: 5 },
				{ target: 'Thursday', distance: 4 },
				{ target: 'Saturday', distance: 3 },
			] );
			expect( bestMatch ).toBe( 'Saturday' );
			expect( bestMatchIndex ).toBe( 2 );
		} );
	} );
} );
