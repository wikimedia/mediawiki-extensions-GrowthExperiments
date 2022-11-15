// REVIEW: why prefix requests aren't made with MenteeOverviewApi?
const API_URL = [
	mw.util.wikiScript( 'rest' ),
	'growthexperiments',
	'v0',
	'mentees',
	'prefixsearch'
].join( '/' );
const LIMIT = 10;

// initial state
const storeState = {
	all: [],
	isReady: false
};

// getters
const getters = {
	allMentees: ( state ) => state.all,
	isReady: ( state ) => state.isReady
};

// actions
const actions = {
	findMenteesByTextQuery: function ( context, options = {} ) {
		context.commit( 'setReady', false );
		return $.getJSON( API_URL + '/' + options.query[ 0 ].toUpperCase() + options.query.slice( 1 ) + '?limit=' + LIMIT )
			.then( ( resp ) => {
				const aggregatedMentees = resp.usernames.map( ( username ) => {
					return {
						label: username,
						value: username
					};
				} );
				context.commit( 'setMentees', aggregatedMentees );
			} ).always( () => {
				context.commit( 'setReady', true );
			} );
	}
};

// mutations
const mutations = {
	setMentees: function ( state, mentees ) {
		state.all = mentees;
	},
	setReady: function ( state, isReady ) {
		state.isReady = isReady;
	}
};

module.exports = exports = {
	// prefixes actions with the module name, ie: mentees-search/findMenteesByTextQuery
	namespaced: true,
	state: storeState,
	getters: getters,
	actions: actions,
	mutations: mutations
};
