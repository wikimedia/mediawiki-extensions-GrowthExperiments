/* global jest:false */
'use strict';
const { config } = require( '@vue/test-utils' );
// Mock Vue plugins in test suites
config.global.mocks = {
	$i18n: ( str ) => {
		return {
			text: () => str,
			parse: () => str
		};
	}
};
config.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${binding.arg} (${binding.value})`;
	}
};

function RestMock() {}
RestMock.prototype.get = jest.fn();

function TitleMock() {}
TitleMock.prototype.getMainText = jest.fn();
TitleMock.prototype.getNameText = jest.fn();
TitleMock.prototype.getUrl = jest.fn();

// Mock MW object
const mw = {
	log: {
		error: jest.fn(),
		warn: jest.fn()
	},
	config: {
		get: jest.fn()
	},
	message: jest.fn( ( key ) => ( {
		text: jest.fn( () => key ),
		parse: jest.fn()
	} ) ),
	user: {
		getId: jest.fn(),
		getName: jest.fn(),
		isAnon: jest.fn().mockReturnValue( true ),
		options: {
			get: jest.fn()
		}
	},
	language: {
		convertNumber: jest.fn( ( x ) => x ),
		getFallbackLanguageChain: function () {
			return [ 'en' ];
		}
	},
	Title: TitleMock,
	util: {
		getUrl: jest.fn()
	},
	Rest: RestMock
	// other mw properties as needed...
};

// Assign things to "global" here if you want them to be globally available during tests
global.$ = require( 'jquery' );
global.mw = mw;
