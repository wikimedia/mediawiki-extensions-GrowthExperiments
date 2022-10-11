'use strict';
/* eslint-disable no-undef */
// Assign things to "global" here if you want them to be globally available during tests
global.$ = require( 'jquery' );

function RestMock() {}
RestMock.prototype.get = jest.fn();

// Mock MW object
global.mw = {
	log: {
		error: jest.fn(),
		warn: jest.fn()
	},
	config: {
		get: jest.fn()
	},
	user: {
		getId: jest.fn(),
		getName: jest.fn(),
		isAnon: jest.fn().mockReturnValue( true ),
		options: {
			get: jest.fn()
		}
	},
	language: {
		getFallbackLanguageChain: function () {
			return [ 'en' ];
		}
	},
	util: {
		getUrl: jest.fn()
	},
	Rest: RestMock
	// other mw properties as needed...
};
