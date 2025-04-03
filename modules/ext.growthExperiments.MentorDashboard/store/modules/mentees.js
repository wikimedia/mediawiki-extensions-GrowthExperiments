const api = require( '../MenteeOverviewApi.js' );
const tagsToFilterBy = require( '../Tags.json' );
const userPreferences = require( './user-preferences.js' );
const MENTEE_OVERVIEW_PRESETS_PREF = 'growthexperiments-mentee-overview-presets';

const createDefaultPresets = () => ( {
	limit: 10,
	editCountMin: 1,
	editCountMax: 500,
	onlyStarred: false,
	activeDaysAgo: undefined
} );

const getInitialPresetsWithFallback = () => {
	let initialPresets = createDefaultPresets();
	try {
		const savedPresets = userPreferences.getters.getPreferenceByName(
			MENTEE_OVERVIEW_PRESETS_PREF
		);
		initialPresets = {
			limit: savedPresets.usersToShow,
			editCountMin: savedPresets.filters.minedits,
			editCountMax: savedPresets.filters.maxedits,
			onlyStarred: savedPresets.filters.onlystarred,
			activeDaysAgo: savedPresets.filters.activedaysago
		};
	} catch ( err ) {
		if ( err instanceof SyntaxError ) {
			mw.log.warn( `Failed parsing JSON stored preference ${ MENTEE_OVERVIEW_PRESETS_PREF }` );
		} else {
			mw.log.error( err );
		}
	}
	return initialPresets;
};

const initalPresets = getInitialPresetsWithFallback();

// initial state
// TODO: consider creating a filter store for all filters
const storeState = {
	all: [],
	hasError: false,
	isReady: false,
	totalPages: 0,
	page: 0,
	prefix: null,
	order: null,
	sortBy: null,
	limit: initalPresets.limit,
	// Dropdown filters
	editCountMin: initalPresets.editCountMin,
	editCountMax: initalPresets.editCountMax,
	onlyStarred: initalPresets.onlyStarred,
	activeDaysAgo: initalPresets.activeDaysAgo

};

// getters
const getters = {
	allMentees: ( state ) => state.all,
	// Pages are 0-index in MenteeOverviewApi, 1-index in the pagination UI
	currentPage: ( state ) => state.page + 1,
	filters: ( { limit, editCountMin, editCountMax, onlyStarred, activeDaysAgo } ) => ( {
		limit,
		editCountMin,
		editCountMax,
		onlyStarred,
		activeDaysAgo
	} ),
	isReady: ( state ) => state.isReady,
	hasError: ( state ) => state.hasError,
	totalPages: ( state ) => state.totalPages,
	doesFilterOutMentees: () => api.doesFilterOutMentees()
};

// Utils
const isBoolean = ( val ) => typeof val === 'boolean';
const isString = ( val ) => typeof val === 'string';
const isEmptyString = ( val ) => val === '';
const removeEmpty = ( obj ) => {
	const newObj = {};
	Object.keys( obj ).forEach( ( key ) => {
		if ( obj[ key ] === Object( obj[ key ] ) ) {
			newObj[ key ] = removeEmpty( obj[ key ] );
		} else if ( obj[ key ] !== undefined ) {
			newObj[ key ] = obj[ key ];
		}
	} );
	return newObj;
};

const filtersToPresets = ( {
	limit, editCountMin, editCountMax, onlyStarred, activeDaysAgo
} ) => ( {
	usersToShow: limit,
	filters: {
		activedaysago: activeDaysAgo,
		maxedits: editCountMax,
		minedits: editCountMin,
		onlystarred: onlyStarred
	}
} );

const filtersToParams = ( {
	editCountMin, editCountMax, onlyStarred, activeDaysAgo, sortBy, order, prefix
} ) => ( {
	order,
	prefix,
	activedaysago: activeDaysAgo,
	maxedits: editCountMax,
	minedits: editCountMin,
	onlystarred: onlyStarred,
	sortby: sortBy
} );

// TODO validate should not commit changes, separate validation
// from mutations
const validateAndApplyFilters = ( context, filters = {} ) => {
	if ( Number.isInteger( filters.page ) ) {
		context.commit( 'SET_PAGE', filters.page );
	}
	if ( Number.isInteger( filters.limit ) ) {
		context.commit( 'SET_LIMIT', filters.limit );
	}
	const validFilters = Object.assign( {}, context.getters.filters );
	if ( filters.prefix === null || isString( filters.prefix ) ) {
		validFilters.prefix = filters.prefix;
	}
	if ( isEmptyString( filters.editCountMin ) || Number.isInteger( filters.editCountMin ) ) {
		validFilters.editCountMin = Number.isInteger( filters.editCountMin ) ?
			filters.editCountMin :
			undefined;
	}
	if ( isEmptyString( filters.editCountMax ) || Number.isInteger( filters.editCountMax ) ) {
		validFilters.editCountMax = Number.isInteger( filters.editCountMax ) ?
			filters.editCountMax :
			undefined;
	}
	if ( isNaN( filters.activeDaysAgo ) || Number.isInteger( filters.activeDaysAgo ) ) {
		validFilters.activeDaysAgo = filters.activeDaysAgo || undefined;
	}
	if ( isBoolean( filters.onlyStarred ) ) {
		validFilters.onlyStarred = filters.onlyStarred;
	}
	if ( isString( filters.sortBy ) ) {
		validFilters.sortBy = filters.sortBy;
	}
	if ( isString( filters.order ) ) {
		validFilters.order = filters.order;
	}
	if ( !$.isEmptyObject( validFilters ) ) {
		context.commit( 'SET_FILTERS', validFilters );
		context.commit( 'SET_API_FILTERS', validFilters );
	}
	return true;
};

const mwLink = ( title, ...args ) => ( {
	href: mw.util.getUrl( title, ...args ),
	text: title
} );

const aggregateMentees = ( mentees, starredMentees ) => mentees.map( ( mentee ) => Object.assign(
	{},
	mentee,
	{
		isStarred: starredMentees.includes( Number( mentee.user_id ) ),
		questions: {
			value: mw.language.convertNumber( mentee.questions ),
			link: mwLink( `Special:Contributions/${ mentee.username }`, {
				tagfilter: tagsToFilterBy.questions.join( '|' )
			} )
		},
		editcount: {
			value: mw.language.convertNumber( mentee.editcount ),
			link: mwLink( `Special:Contributions/${ mentee.username }` )
		},
		reverted: {
			value: mw.language.convertNumber( mentee.reverted ),
			link: mwLink( `Special:Contributions/${ mentee.username }`, {
				tagfilter: tagsToFilterBy.reverted.join( '|' )
			} )
		},
		blocks: {
			value: mw.language.convertNumber( mentee.blocks ),
			link: mwLink( 'Special:Log/block', {
				page: 'User:' + mentee.username
			} )
		}
	}
) );

// actions
const actions = {
	getAllMentees: function ( context, options = {} ) {
		context.commit( 'SET_READY', false );

		if ( !validateAndApplyFilters( context, options ) ) {
			context.commit( 'SET_READY', true );
			return;
		}

		return $.when(
			api.getMenteeData(),
			api.getStarredMentees()
		).then( ( mentees, starredMentees ) => {
			// api.getTotalPages() needs to be run after api.getMenteeData(), consider
			// aggregating the pages count to mentee data response
			const totalPages = api.getTotalPages();
			const aggregatedMentees = aggregateMentees( mentees, starredMentees );

			context.commit( 'SET_PAGES', totalPages );
			context.commit( 'SET_MENTEES', aggregatedMentees );
			context.commit( 'SET_ERROR', false );
		} ).catch( ( err ) => {
			context.dispatch( 'recordError', err.responseJSON );
		} ).always( () => {
			context.commit( 'SET_READY', true );
		} );
	},
	savePresets: function ( context ) {
		const menteeOverviewPresets = filtersToPresets( context.getters.filters );
		const cleanedPresets = removeEmpty( menteeOverviewPresets );
		if ( $.isEmptyObject( cleanedPresets.filters ) ) {
			delete cleanedPresets.filters;
		}

		return context.dispatch( 'userPreferences/saveOption', {
			name: MENTEE_OVERVIEW_PRESETS_PREF,
			value: cleanedPresets
		}, { root: true } );
	},
	recordError: function ( context, errorDetails ) {
		context.commit( 'SET_ERROR', true );

		mw.notify(
			mw.message( 'growthexperiments-mentor-dashboard-mentee-overview-error-notification' ).text(),
			{ type: 'error' }
		);

		mw.log.error( 'Unable to fetch mentees: ' + JSON.stringify( errorDetails ) );
		mw.errorLogger.logError(
			new Error( 'Unable to fetch mentees: ' + JSON.stringify( errorDetails ) ),
			'error.growthexperiments'
		);
	}
};

// mutations
const mutations = {
	SET_MENTEES: function ( state, mentees ) {
		state.all = mentees;
	},
	SET_PAGES: function ( state, count ) {
		state.totalPages = count;
	},
	SET_READY: function ( state, isReady ) {
		state.isReady = isReady;
	},
	// TODO: Consider moving mutations that affect API to another store
	// TODO: Consider moving API params state outside of API
	SET_PAGE: function ( state, page ) {
		// Pages are 0-index based in the api client
		api.setPage( page - 1 );
		state.page = page - 1;
	},
	SET_LIMIT: function ( state, limit ) {
		api.setLimit( limit );
		state.limit = limit;
	},
	SET_PREFIX: function ( state, prefix ) {
		api.setPrefix( prefix );
		state.prefix = prefix;
	},
	SET_FILTERS: function ( state, update ) {
		Object.keys( update ).forEach( ( key ) => {
			state[ key ] = update[ key ];
		} );
	},
	SET_API_FILTERS: function ( state, filters ) {
		const params = filtersToParams( filters );
		api.setFilters( params );
	},
	SET_ERROR: function ( state, hasError ) {
		state.hasError = hasError;
	}
};

module.exports = exports = {
	// Exported for testing purposes
	MENTEE_OVERVIEW_PRESETS_PREF,
	api,
	createDefaultPresets,
	getInitialPresetsWithFallback,
	validateAndApplyFilters,
	// prefixes actions with the module name, ie: mentees/getAllMentees
	namespaced: true,
	state: storeState,
	getters: getters,
	actions: actions,
	mutations: mutations
};
