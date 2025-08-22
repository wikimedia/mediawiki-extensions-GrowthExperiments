/**
 * @param {string} str1
 * @param {string} str2
 * @return {number}
 */
function compareTwoStrings( str1, str2 ) {
	if ( str1 === str2 ) {
		return 0;
	}

	if ( str1.length === 0 ) {
		return str2.length;
	} else if ( str2.length === 0 ) {
		return str1.length;
	}

	/** @type {number[]} */
	let previousRow = [];
	for ( let i = 0; i < str2.length + 1; i++ ) {
		previousRow[ i ] = i;
	}

	/** @type {number[]} */
	let currentRow = [];

	for ( let i = 0; i < str1.length; i++ ) {
		currentRow[ 0 ] = i + 1;

		for ( let j = 0; j < str2.length; j++ ) {
			const deleteCost = previousRow[ j + 1 ] + 1;
			const insertCost = currentRow[ j ] + 1;

			let substituteCost;
			if ( str1[ i ] === str2[ j ] ) {
				substituteCost = previousRow[ j ];
			} else {
				substituteCost = previousRow[ j ] + 1;
			}

			currentRow[ j + 1 ] = Math.min( deleteCost, insertCost, substituteCost );
		}

		[ previousRow, currentRow ] = [ currentRow, previousRow ];
	}

	return previousRow[ str2.length ];
}

/**
 * @param {string} mainString
 * @param {string[]} targetStrings
 * @return {{ bestMatchIndex: number, bestMatch: string, ratings: { target: string, distance: number }[] }}
 */
function findBestMatch( mainString, targetStrings ) {
	const ratings = targetStrings.map( ( target ) => ( {
		target,
		distance: compareTwoStrings( mainString, target ),
	} ) );
	const bestMatchIndex = ratings.reduce( (
		bestIndex,
		rating,
		currentIndex,
		array,
	) => rating.distance < array[ bestIndex ].distance ? currentIndex : bestIndex, 0 );
	return {
		bestMatchIndex,
		bestMatch: ratings[ bestMatchIndex ].target,
		ratings,
	};
}

module.exports = {
	compareTwoStrings,
	findBestMatch,
};
