'use strict';

const pathToWidget = '../../../../modules/ext.growthExperiments.StructuredTask/addlink/AddLinkArticleTarget.js',
	pathToLogger = '../../../../modules/ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js';

QUnit.module( 'ext.growthExperiments.StructuredTask/addlink/AddLinkArticleTarget.js', QUnit.newMwEnvironment() );

QUnit.test( 'annotateSuggestions', function ( assert ) {
	const AddLinkArticleTarget = require( pathToWidget );
	const LinkSuggestionInteractionLogger = require( pathToLogger );
	const data = require( './dataprovider.json' );
	data.forEach( function ( fixture ) {
		const articleTarget = new AddLinkArticleTarget( new LinkSuggestionInteractionLogger() );
		const doc = document.implementation.createHTMLDocument();
		const body = document.createElement( 'body' );
		fixture.articleContent.forEach( function ( item ) {
			const newElement = document.createElement( item.element );
			const newContent = document.createTextNode( item.content );
			newElement.append( newContent );
			body.append( newElement );
		} );
		doc.body = body;
		articleTarget.annotateSuggestions( doc, fixture.suggestions );
		assert.strictEqual(
			// eslint-disable-next-line no-restricted-properties
			doc.body.innerHTML.replaceAll( '&lt;', '<' ).replaceAll( '&gt;', '>' ),
			fixture.annotatedBody
		);
	} );

} );
