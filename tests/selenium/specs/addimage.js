'use strict';

const isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddImageArticlePage = require( '../pageobjects/addimage.article.page' );

describe( 'add image', function () {

	it( 'user can view image info and image details', async function () {
		const addImageArticle = "Ma'amoul";
		if ( !isQuibbleUsingApache ) {
			this.skip( 'This test depends on using PHP-FPM and Apache as the backend.' );
		}
		await AddImageArticlePage.setup( addImageArticle );

		await AddImageArticlePage.viewImageInfo();
		await AddImageArticlePage.closeImageInfo();

		await AddImageArticlePage.acceptSuggestion();

		await AddImageArticlePage.viewImageDetails();
		await AddImageArticlePage.closeImageDetails();
	} );

} );
