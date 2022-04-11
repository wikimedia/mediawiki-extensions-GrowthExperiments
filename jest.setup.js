'use strict';
/* eslint-disable no-undef */
// Assign things to "global" here if you want them to be globally available during tests
global.$ = require( 'jquery' );

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
		isAnon: jest.fn().mockReturnValue( true ),
		options: {
			get: jest.fn()
		}
	},
	language: {
		convertNumber: jest.fn(),
		getFallbackLanguageChain: function () {
			return [ 'en' ];
		}
	}
	// other mw properties as needed...
};
