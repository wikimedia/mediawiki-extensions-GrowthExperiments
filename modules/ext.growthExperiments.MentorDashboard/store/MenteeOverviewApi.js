/**
 * Client for mentee REST API
 */
( function () {
	'use strict';

	function MenteeOverviewApi() {
		// api URL
		this.apiUrl = [
			mw.config.get( 'wgScriptPath' ),
			'rest.php',
			'growthexperiments',
			'v0',
			'mentees'
		].join( '/' );

		this.apiParams = {
			order: 'desc',
			sortby: 'last_active',
			limit: 10,
			offset: 0
		};
		this.nonFilterKeys = [ 'order', 'sortby', 'limit', 'offset', 'uselang' ];
		this.page = 0;
		this.totalRows = null;
		this.assignedMentees = null;

		this.starredMentees = null;
	}

	MenteeOverviewApi.prototype.applyApiParams = function ( params ) {
		this.apiParams = Object.assign( this.apiParams, params );
	};

	MenteeOverviewApi.prototype.setFilters = function ( filters ) {
		// First, delete all filtering API params
		const menteeOverviewApi = this;
		Object.keys( this.apiParams ).forEach( ( key ) => {
			if ( !menteeOverviewApi.nonFilterKeys.includes( key ) ) {
				delete menteeOverviewApi.apiParams[ key ];
			}
		} );

		// Drop undefined/null filters because of T372164
		Object.keys( filters ).forEach( ( key ) => {
			if ( filters[ key ] === undefined || filters[ key ] === null ) {
				delete filters[ key ];
			}
		} );

		// Then, apply passed ones
		this.applyApiParams( filters );
	};

	MenteeOverviewApi.prototype.hasFilters = function () {
		const menteeOverviewApi = this;
		let res = false;
		Object.keys( this.apiParams ).every( ( key ) => {
			if ( res ) {
				return false;
			}

			if ( !menteeOverviewApi.nonFilterKeys.includes( key ) ) {
				res = true;
				return false;
			}

			return true;
		} );

		return res;
	};

	/**
	 * Are there any mentees filtered out by currently applied filters?
	 *
	 * hasFilters tells whether there are any filters applied at all. In certain
	 * cases, however, even though some filters are set, they do not filter out any mentee.
	 *
	 * @return {boolean}
	 */
	MenteeOverviewApi.prototype.doesFilterOutMentees = function () {
		return this.totalRows !== this.assignedMentees;
	};

	MenteeOverviewApi.prototype.setPrefix = function ( prefix ) {
		this.apiParams.prefix = prefix;
	};

	MenteeOverviewApi.prototype.setLimit = function ( value ) {
		this.apiParams.limit = value;
	};

	MenteeOverviewApi.prototype.getLimit = function () {
		return this.apiParams.limit;
	};

	MenteeOverviewApi.prototype.setPage = function ( value ) {
		this.page = value;
	};

	MenteeOverviewApi.prototype.getMenteeData = function () {
		const menteeOverviewApi = this;

		this.apiParams.offset = this.page * this.apiParams.limit;
		this.apiParams.uselang = mw.config.get( 'wgUserLanguage' );
		return $.getJSON( this.apiUrl + '?' + $.param( this.apiParams ) ).then( ( data ) => {
			menteeOverviewApi.totalRows = data.totalRows;
			menteeOverviewApi.assignedMentees = data.assignedMentees;
			return data.mentees;
		} );
	};

	MenteeOverviewApi.prototype.getTotalPages = function () {
		if ( this.totalRows === null ) {
			return null;
		}
		return Math.ceil( this.totalRows / this.apiParams.limit );
	};

	MenteeOverviewApi.prototype.getStarredMentees = function () {
		const menteeOverviewApi = this;
		if ( this.starredMentees !== null ) {
			return $.Deferred().resolve( this.starredMentees ).promise();
		} else {
			return this.getStarredMenteesAPI().then( ( mentees ) => {
				menteeOverviewApi.starredMentees = mentees;
				return mentees;
			} );
		}
	};

	MenteeOverviewApi.prototype.getStarredMenteesAPI = function () {
		return ( new mw.Api().get( {
			action: 'query',
			list: 'growthstarredmentees'
		} ) ).then( ( data ) => {
			const mentees = [];
			for ( let i = 0; i < data.growthstarredmentees.mentees.length; i++ ) {
				const menteeId = Number( data.growthstarredmentees.mentees[ i ].id );
				if ( !mentees.includes( menteeId ) ) {
					mentees.push( menteeId );
				}
			}
			return mentees;
		} );
	};

	MenteeOverviewApi.prototype.starMentee = function ( userId ) {
		const menteeOverviewApi = this;
		return new mw.Api().postWithToken( 'csrf', {
			action: 'growthstarmentee',
			gesaction: 'star',
			gesmentee: '#' + userId
		} ).then(
			// Do not use this.starredMentees directly, as that might not be inited yet
			() => menteeOverviewApi.getStarredMentees().then(
				// REVIEW consider rephrasing the comment.
				// In case GetStarredMentees fallbacked to API, this is actually
				// not neeeded. Since this is a set, it doesn't matter much.
				() => menteeOverviewApi.starredMentees.push( Number( userId ) )
			)
		);
	};

	MenteeOverviewApi.prototype.unstarMentee = function ( userId ) {
		const menteeOverviewApi = this;
		return new mw.Api().postWithToken( 'csrf', {
			action: 'growthstarmentee',
			gesaction: 'unstar',
			gesmentee: '#' + userId
		} ).then(
			// Do not use this.starredMentees directly, as that might not be inited yet
			() => menteeOverviewApi.getStarredMentees().then( () => {
				// Remove mentee ID from list of starred mentees; In case GetStarredMentees
				// fallbacked to API, this is actually not necessary, but it shouldn't hurt.
				menteeOverviewApi.starredMentees = menteeOverviewApi.starredMentees.filter(
					( el ) => el !== Number( userId )
				);
			} )
		);
	};

	MenteeOverviewApi.prototype.isMenteeStarred = function ( userId ) {
		return this.getStarredMentees().then(
			( mentees ) => mentees.includes( Number( userId ) )
		);
	};

	module.exports = new MenteeOverviewApi();
}() );
