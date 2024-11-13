// @ts-nocheck - TODO: make this covered by typescript
'use strict';

const ArticleTextManipulator = require( './ArticleTextManipulator.js' );

describe( 'ArticleTextManipulator', () => {
	describe( 'findFirstParagraphContainingText', () => {
		it( 'returns null if string is not found', () => {
			const sut = new ArticleTextManipulator();
			const result = sut.findFirstParagraphContainingText( document.createElement( 'p' ), 'foo' );
			expect( result ).toBeNull();
		} );

		it( 'returns the first paragraph containing the text', () => {
			const sut = new ArticleTextManipulator();
			const rootElement = document.createElement( 'div' );
			const paragraph1 = document.createElement( 'p' );
			const paragraph2 = document.createElement( 'p' );
			const paragraph3 = document.createElement( 'p' );
			paragraph1.textContent = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce eu mauris';
			paragraph2.textContent = 'Suspendisse potenti. Donec pharetra, quam sit amet mollis dapibus';
			paragraph3.textContent = 'Aliquam feugiat metus ut neque vehicula commodo. Donec non libero tortor.';
			rootElement.appendChild( paragraph1 );
			rootElement.appendChild( paragraph2 );
			rootElement.appendChild( paragraph3 );
			const result = sut.findFirstParagraphContainingText( rootElement, 'Donec' );
			expect( result ).toBe( paragraph2 );
		} );
	} );

	describe( 'replaceDirectTextWithElement', () => {
		it( 'replaces a word inside the text of paragraph with the provided element', () => {
			const sut = new ArticleTextManipulator();
			const paragraph = document.createElement( 'p' );
			paragraph.textContent = 'foo bar baz';
			const textToReplace = 'bar';
			const element = document.createElement( 'span' );
			element.textContent = 'bar';
			sut.replaceDirectTextWithElement( paragraph, textToReplace, element );
			expect( paragraph.innerHTML ).toBe( 'foo <span>bar</span> baz' );
		} );

		it( 'does not affect existing HTML elements or js events bound to them', () => {
			const sut = new ArticleTextManipulator();
			const paragraph = document.createElement( 'p' );
			const elementThatShouldNotBeTouched = document.createElement( 'button' );
			elementThatShouldNotBeTouched.className = 'unique-class';
			paragraph.innerHTML = 'foo bar baz';
			paragraph.appendChild( elementThatShouldNotBeTouched );
			const textToReplace = 'bar';
			const element = document.createElement( 'span' );
			element.textContent = 'bar';
			sut.replaceDirectTextWithElement( paragraph, textToReplace, element );
			expect( paragraph.innerHTML ).toBe( 'foo <span>bar</span> baz<button class="unique-class"></button>' );
			expect( paragraph.getElementsByClassName( 'unique-class' )[ 0 ] ).toBe( elementThatShouldNotBeTouched );
		} );
	} );
} );
