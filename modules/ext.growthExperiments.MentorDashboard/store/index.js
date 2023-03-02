const createStore = require( 'vuex' ).createStore;
const mentees = require( './modules/mentees.js' );
const menteesSearch = require( './modules/mentees-search.js' );
const userPreferences = require( './modules/user-preferences.js' );
const praiseworthyMentees = require( './modules/praiseworthy-mentees.js' );

const store = createStore( {
	modules: {
		mentees,
		menteesSearch,
		userPreferences,
		praiseworthyMentees
	}
} );

module.exports = exports = store;
