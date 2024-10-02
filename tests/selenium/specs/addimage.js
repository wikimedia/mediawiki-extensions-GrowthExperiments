'use strict';

const isQuibbleUsingApache = process.env.QUIBBLE_APACHE || false,
	AddImageArticlePage = require( '../pageobjects/addimage.article.page' ),
	addImageArticlePage = new AddImageArticlePage(),
	AddImageArticleMobilePage = require( '../pageobjects/addimage.article.mobile.page' ),
	addImageArticleMobilePage = new AddImageArticleMobilePage();

describe( 'add image', () => {

	beforeEach( async function () {
		if ( !isQuibbleUsingApache ) {
			this.skip( 'This test depends on using PHP-FPM and Apache as the backend.' );
		}
	} );

	it( 'desktop: user can view image info and image details', async () => {
		const addImageArticle = "Ma'amoul";
		await addImageArticlePage.setup( addImageArticle );

		await addImageArticlePage.viewImageInfo();
		await addImageArticlePage.closeImageInfo();

		await addImageArticlePage.acceptSuggestion();

		await addImageArticlePage.viewImageDetails();
		await addImageArticlePage.closeImageDetails();

		await addImageArticlePage.switchToReadMode();
		await addImageArticlePage.discardEdits();
	} );

	// Skipped on 2023-05-20 in 921608 because of T334626 and T337137
	it.skip( 'mobile: user can close the image suggestion UI', async () => {
		const addImageArticle = "Ma'amoul";
		await addImageArticlePage.setup( addImageArticle, 'mobile' );
		await addImageArticleMobilePage.closeImageInspector();
	} );

} );
