const { quantizeViews } = require( './useUserImpact.js' );

const createArticleViews = ( numberOfItems ) => new Array( numberOfItems )
	.fill( 0 )
	.map( () => ( { views: Math.ceil( Math.random() * 1000 ) } ) );

describe( 'quantizeViews', () => {
	it( 'should create 6 data points', () => {
		let result = quantizeViews( createArticleViews( 3 ) );
		expect( result ).toHaveLength( 3 );
		result = quantizeViews( createArticleViews( 7 ) );
		expect( result ).toHaveLength( 6 );
	} );
} );
