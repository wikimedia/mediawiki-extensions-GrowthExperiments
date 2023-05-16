// The store does not hold preference data since it would be a
// duplication of values in mw.user.options. It just handles
// the loading state for the save requests.

// initial state
const storeState = {
	isLoading: false
};

const personalizedPraiseSettings = mw.config.get( 'GEPersonalizedPraiseSettings' );
if ( personalizedPraiseSettings ) {
	mw.user.options.set( 'growthexperiments-personalized-praise-settings', personalizedPraiseSettings );
}

// getters
const getters = {
	getPreferenceByName( name ) {
		const json = mw.user.options.get( name );
		return JSON.parse( json );
	},
	isLoading: ( state ) => state.isLoading
};

// actions
const actions = {
	saveOption( context, payload ) {
		context.commit( 'setLoading', true );
		const update = Object.assign( {}, payload.value );
		const serializedUpdate = JSON.stringify( update );
		return new mw.Api().saveOption( payload.name, serializedUpdate )
			.always( () => {
				context.commit( 'setLoading', false );
			} );
	}

};

// mutations
const mutations = {
	setLoading: function ( state, isLoading ) {
		state.isLoading = isLoading;
	}
};

module.exports = exports = {
	// prefixes actions with the module name, ie: user-preferences/saveOption
	namespaced: true,
	state: storeState,
	getters: getters,
	actions: actions,
	mutations: mutations
};
