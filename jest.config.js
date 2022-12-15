'use strict';
/*
 * For a detailed explanation regarding each configuration property, visit:
 * https://jestjs.io/docs/configuration
 */

module.exports = {
	// Vue-jest specific global options (described here: https://github.com/vuejs/vue-jest#global-jest-options)
	globals: {
		babelConfig: false,
		hideStyleWarn: true,
		experimentalCssCompile: true
	},
	// This and "transform" below are the most crucial for vue-jest:
	// https://github.com/vuejs/vue-jest#setup
	moduleFileExtensions: [
		'js',
		'json',
		'vue'
	],
	transform: {
		'.*\\.(vue)$': '<rootDir>/node_modules/@vue/vue3-jest'
	},
	testEnvironment: 'jsdom',
	// Indicates whether the coverage information should be collected while executing the test
	collectCoverage: true,
	collectCoverageFrom: [
		'modules/ext.growthExperiments.MentorDashboard/**/*.(js|vue)',
		'modules/ext.growthExperiments.Homepage.NewImpact/**/*.(js|vue)',
		'modules/vue-components/**/*.(js|vue)'
	],
	// The directory where Jest should output its coverage files
	coverageDirectory: 'coverage',
	// A list of paths to directories that Jest should use to search for files in.
	roots: [
		'./modules/ext.growthExperiments.MentorDashboard',
		'./modules/ext.growthExperiments.Homepage.NewImpact',
		'./modules/vue-components'
	],
	setupFiles: [
		'./jest.setup.js'
	]
};
