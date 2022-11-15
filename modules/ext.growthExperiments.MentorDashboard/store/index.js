const createStore = require( 'vuex' ).createStore;
const mentees = require( './modules/mentees.js' );
const menteesSearch = require( './modules/mentees-search.js' );
const userPreferences = require( './modules/user-preferences.js' );

const store = createStore( {
	modules: {
		mentees,
		menteesSearch,
		userPreferences
	}
} );

module.exports = exports = store;
