/**
 * Entry point for accessing store modules and constants (once Vue migration happens, this class
 * can be replaced by the Vuex store)
 *
 * @typedef {Object} mw.libs.ge.DataStore
 *
 * @property {mw.libs.ge.NewcomerTasksStore} newcomerTasks
 * @property {Object} CONSTANTS
 */
module.exports = {
	newcomerTasks: require( './store.js' ).newcomerTasks,
	CONSTANTS: require( './constants.js' ),
};
