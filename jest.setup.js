/* global jest:false */
'use strict';
const { config } = require( '@vue/test-utils' );

/**
 * Mock for the calls to MW core's i18n plugin which returns
 * a mw.Message object.
 *
 * @param {string} key The text key to parse
 * @param {...*} args Arbitrary number of arguments to be parsed
 * @return {mw.growthTests.MwMessageInterface}
 */
function $i18nMock( key, ...args ) {
	function serializeArgs() {
		return args.length ? `${ key }:[${ args.join( ',' ) }]` : key;
	}
	/**
	 * mw.Message-like object with .text() and .parse() method
	 *
	 * @typedef {Object} mw.growthTests.MwMessageInterface
	 *
	 * @property {Function} text parses the given banana message
	 * @property {Function} parse parses the given banana message (without html support)
	 */
	return {
		text: () => serializeArgs(),
		parse: () => serializeArgs(),
	};
}
// Mock Vue plugins in test suites
config.global.provide = {
	i18n: $i18nMock,
};
config.global.mocks = {
	$i18n: $i18nMock,
};
config.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${ binding.arg } (${ binding.value })`;
	},
};

function RestMock() {}
RestMock.prototype.get = jest.fn();

function TitleMock() {}
TitleMock.prototype.getMainText = jest.fn();
TitleMock.prototype.getNameText = jest.fn();
TitleMock.prototype.getUrl = jest.fn();

const xLabMock = {
	getExperiment() {
		return {
			getAssignedGroup: jest.fn(),
		};
	},
};

// Mock MW object
const mw = {
	log: {
		error: jest.fn(),
		warn: jest.fn(),
	},
	config: {
		get: jest.fn(),
		set: jest.fn(),
	},
	message: jest.fn( ( key ) => ( {
		text: jest.fn( () => key ),
		parse: jest.fn(),
	} ) ),
	user: {
		getId: jest.fn(),
		getName: jest.fn(),
		isAnon: jest.fn().mockReturnValue( true ),
		options: {
			get: jest.fn(),
		},
	},
	language: {
		convertNumber: jest.fn( ( x ) => x ),
		getFallbackLanguageChain: function () {
			return [ 'en' ];
		},
	},
	Title: TitleMock,
	util: {
		getUrl: jest.fn(),
	},
	Rest: RestMock,
	xLab: xLabMock,
	// other mw properties as needed...
};

// Make calls to OO.EventEmitter.call( this ) provide a this.emit() method
function EventEmitterMock() {
	this.emit = jest.fn();
}
function OOMock() {}
OOMock.mixinClass = jest.fn();
OOMock.EventEmitter = EventEmitterMock;
OOMock.ui = {
	isMobile: jest.fn(),
};

// Assign things to "global" here if you want them to be globally available during tests
global.$ = require( 'jquery' );
global.mw = mw;
global.OO = OOMock;
