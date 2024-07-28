'use strict';

const AddImageArticlePage = require( './addimage.article.page' );

class AddimageArticleMobilePage extends AddImageArticlePage {

	get closeButton() {
		return $( '.oo-ui-tool-name-back' );
	}

	async closeImageInspector() {
		await this.clickButton( this.closeButton );
	}
}

module.exports = AddimageArticleMobilePage;
