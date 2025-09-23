'use strict';

const pathToWidget = '../../../../modules/ext.growthExperiments.StructuredTask/addlink/AddLinkArticleTarget.js',
	pathToLogger = '../../../../modules/ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js';

QUnit.module( 'ext.growthExperiments.StructuredTask/addlink/AddLinkArticleTarget.js', QUnit.newMwEnvironment() );

QUnit.test( 'annotateSuggestions', function ( assert ) {
	const AddLinkArticleTarget = require( pathToWidget );
	const LinkSuggestionInteractionLogger = require( pathToLogger );
	const data = require( './dataprovider.json' );
	const MAX_LINKS_TO_SHOW = 2;

	this.sandbox.stub( LinkSuggestionInteractionLogger.prototype, 'log' ).returns( true );

	data.forEach( ( fixture ) => {
		const articleTarget = new AddLinkArticleTarget(
			new LinkSuggestionInteractionLogger(),
		);
		articleTarget.maximumLinksToShow = MAX_LINKS_TO_SHOW;
		const doc = document.implementation.createHTMLDocument();
		const body = document.createElement( 'body' );
		fixture.articleContent.forEach( ( item ) => {
			const newElement = document.createElement( item.element );
			const newContent = document.createTextNode( item.content );
			newElement.append( newContent );
			body.append( newElement );
		} );
		doc.body = body;
		articleTarget.annotateSuggestions( doc, fixture.suggestions );
		assert.strictEqual(
			doc.body.innerHTML.replaceAll( '&lt;', '<' ).replaceAll( '&gt;', '>' ),
			fixture.annotatedBody,
		);

		assert.strictEqual( LinkSuggestionInteractionLogger.prototype.log.calledOnce, true );
		assert.strictEqual( LinkSuggestionInteractionLogger.prototype.log.firstCall.args[ 0 ], 'impression' );
		assert.deepEqual( LinkSuggestionInteractionLogger.prototype.log.firstCall.args[ 1 ], {
			/* eslint-disable camelcase */
			number_phrases_expected: 4,
			number_phrases_found: 3,
			number_phrases_shown: 2,
			/* eslint-enable camelcase */
		} );
	} );

} );
