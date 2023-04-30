const userPreferences = require( './user-preferences.js' );
const PERSONALIZED_PRAISE_SETTINGS_PREF = 'growthexperiments-personalized-praise-settings';

const getInitialSettings = () => {
	let savedSettings;
	try {
		savedSettings = userPreferences.getters.getPreferenceByName(
			PERSONALIZED_PRAISE_SETTINGS_PREF
		);
	} catch ( err ) {
		if ( err instanceof SyntaxError ) {
			mw.log.warn( `Failed parsing JSON stored preference ${PERSONALIZED_PRAISE_SETTINGS_PREF}` );
		} else {
			mw.log.error( err );
		}
	}

	return savedSettings;
};

// initial state
const storeState = {
	mentees: [],
	currentPage: 0,
	settings: getInitialSettings()
};

// getters
const getters = {
	totalPages: ( state ) => state.mentees.length,
	// pages are 0-index in state.mentees, 1-index in the pagination UI
	currentPage: ( state ) => state.currentPage + 1,
	mentee: ( state ) => state.mentees[ state.currentPage ],
	settings: ( state ) => state.settings
};

// actions
const actions = {
	fetchMentees: function ( context ) {
		context.commit( 'SET_MENTEES', mw.config.get( 'GEPraiseworthyMentees' ) );
	},
	removeMentee: function ( context, mentee ) {
		context.commit( 'REMOVE_MENTEE', mentee.userId );

		if ( context.getters.currentPage > context.state.mentees.length ) {
			context.commit( 'SET_PAGE', context.state.mentees.length );
		}
	},
	previousPage: function ( context ) {
		if ( context.getters.currentPage <= 1 ) {
			return;
		}

		context.commit( 'SET_PAGE', context.getters.currentPage - 1 );
	},
	nextPage: function ( context ) {
		if ( context.getters.currentPage >= context.state.mentees.length ) {
			return;
		}

		context.commit( 'SET_PAGE', context.getters.currentPage + 1 );
	},
	saveSettings: function ( context, settings ) {
		context.commit( 'SET_SETTINGS', settings );
		new mw.Api().postWithToken( 'csrf', {
			action: 'edit',
			title: mw.config.get( 'GEPraiseworthyMessageUserTitle' ),
			text: settings.messageText || ''
		} );
		context.dispatch( 'userPreferences/saveOption', {
			name: PERSONALIZED_PRAISE_SETTINGS_PREF,
			value: settings
		}, { root: true } );
	}
};

// mutations
const mutations = {
	SET_MENTEES: function ( state, mentees ) {
		state.mentees = mentees;
	},
	REMOVE_MENTEE: function ( state, userId ) {
		for ( let i = 0; i < state.mentees.length; i++ ) {
			if ( userId === state.mentees[ i ].userId ) {
				state.mentees.splice( i, 1 );
				break;
			}
		}
	},
	SET_PAGE: function ( state, page ) {
		if ( page > 0 && page <= state.mentees.length ) {
			// pages are 0-index in state.mentees, 1-index in the pagination UI
			state.currentPage = page - 1;
		}
	},
	SET_SETTINGS: function ( state, settings ) {
		state.settings = settings;
	}
};

module.exports = exports = {
	namespaced: true,
	state: storeState,
	getters: getters,
	actions: actions,
	mutations: mutations
};
