'use strict';

const pathToWidget = '../../../modules/ext.growthExperiments.Homepage.SuggestedEdits/ErrorCardWidget.js';

QUnit.module( 'ext.growthExperiments.Homepage.SuggestedEdits/ErrorCardWidget.js', QUnit.newMwEnvironment() );

QUnit.test( 'constructor', function ( assert ) {
	const ErrorCardWidget = require( pathToWidget );
	const errorCardWidget = new ErrorCardWidget();
	const title = '⧼growthexperiments-homepage-suggestededits-error-title⧽';
	const description = '⧼growthexperiments-homepage-suggestededits-error-description⧽';
	const expectedHtml = '<div class="se-card-error"><h3 class="se-card-title">' + title +
		'</h3><div class="se-card-image"></div><p class="se-card-text">' + description + '</p></div>';
	assert.strictEqual( errorCardWidget.$element.html(), expectedHtml );
} );
