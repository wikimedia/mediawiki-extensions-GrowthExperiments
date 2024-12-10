// @ts-nocheck - TODO: make this covered by typescript
'use strict';

const ArticleTextManipulator = require( './ArticleTextManipulator.js' );

describe( 'ArticleTextManipulator', () => {
	describe( 'findFirstParagraphContainingText', () => {
		it( 'returns null if string is not found', () => {
			const sut = new ArticleTextManipulator();
			const result = sut.findFirstContentElementContainingText( document.createElement( 'p' ), 'foo' );
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
			const result = sut.findFirstContentElementContainingText( rootElement, 'Donec' );
			expect( result ).toBe( paragraph2 );
		} );

		it( 'returns also list items if they have matching text content', () => {
			const sut = new ArticleTextManipulator();
			const rootElement = document.createElement( 'div' );
			const listWrapper = document.createElement( 'ul' );
			rootElement.appendChild( listWrapper );
			const listItem1 = document.createElement( 'li' );
			const listItem2 = document.createElement( 'li' );
			const listItem3 = document.createElement( 'li' );
			listItem1.textContent = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce eu mauris';
			listItem2.textContent = 'Suspendisse potenti. Donec pharetra, quam sit amet mollis dapibus';
			listItem3.textContent = 'Aliquam feugiat metus ut neque vehicula commodo. Donec non libero tortor.';
			listWrapper.appendChild( listItem1 );
			listWrapper.appendChild( listItem2 );
			listWrapper.appendChild( listItem3 );
			const result = sut.findFirstContentElementContainingText( rootElement, 'Donec' );
			expect( result ).toBe( listItem2 );
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
