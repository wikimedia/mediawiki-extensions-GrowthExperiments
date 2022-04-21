'use strict';

var FiltersStore = require( './FiltersStore.js' ),
	NewcomerTasksStore = require( './NewcomerTasksStore.js' );

/**
 * @typedef {Object} mw.libs.ge.DataStore.store
 *
 * @property {mw.libs.ge.FiltersStore} filters
 * @property {mw.libs.ge.NewcomerTasksStore} newcomerTasks
 */
( function () {
	/** @type {mw.libs.ge.DataStore.store} **/
	var store = {};
	store.filters = new FiltersStore();
	store.newcomerTasks = new NewcomerTasksStore( store );
	module.exports = store;
}() );
