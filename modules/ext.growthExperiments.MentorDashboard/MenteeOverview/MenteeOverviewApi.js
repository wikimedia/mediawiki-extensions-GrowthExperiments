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
		this.apiParams = $.extend( this.apiParams, params );
	};

	MenteeOverviewApi.prototype.setFilters = function ( filters ) {
		// First, delete all filtering API params
		var menteeOverviewApi = this;
		Object.keys( this.apiParams ).forEach( function ( key ) {
			if ( menteeOverviewApi.nonFilterKeys.indexOf( key ) === -1 ) {
				delete menteeOverviewApi.apiParams[ key ];
			}
		} );

		// Then, apply passed ones
		this.applyApiParams( filters );
	};

	MenteeOverviewApi.prototype.hasFilters = function () {
		var menteeOverviewApi = this;
		var res = false;
		Object.keys( this.apiParams ).every( function ( key ) {
			if ( res ) {
				return false;
			}

			if ( menteeOverviewApi.nonFilterKeys.indexOf( key ) === -1 ) {
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
	 * @returns {boolean}
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
		this.apiParams.offset = this.page * this.apiParams.limit;
	};

	MenteeOverviewApi.prototype.getMenteeData = function () {
		var menteeOverviewApi = this;

		this.apiParams.uselang = mw.config.get( 'wgUserLanguage' );
		return $.getJSON( this.apiUrl + '?' + $.param( this.apiParams ) ).then( function ( data ) {
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
		var menteeOverviewApi = this;
		if ( this.starredMentees !== null ) {
			return $.Deferred().resolve( this.starredMentees ).promise();
		} else {
			return this.getStarredMenteesAPI().then( function ( mentees ) {
				menteeOverviewApi.starredMentees = mentees;
				return mentees;
			} );
		}
	};

	MenteeOverviewApi.prototype.getStarredMenteesAPI = function () {
		return ( new mw.Api().get( {
			action: 'query',
			list: 'growthstarredmentees'
		} ) ).then( function ( data ) {
			var mentees = [];
			for ( var i = 0; i < data.growthstarredmentees.mentees.length; i++ ) {
				var menteeId = Number( data.growthstarredmentees.mentees[ i ].id );
				if ( mentees.indexOf( menteeId ) === -1 ) {
					mentees.push( menteeId );
				}
			}
			return mentees;
		} );
	};

	MenteeOverviewApi.prototype.starMentee = function ( userId ) {
		var menteeOverviewApi = this;
		return new mw.Api().postWithToken( 'csrf', {
			action: 'growthstarmentee',
			gesaction: 'star',
			gesmentee: '#' + userId
		} ).then( function () {
			// Do not use this.starredMentees directly, as that might not be inited yet
			return menteeOverviewApi.getStarredMentees().then( function () {
				// In case GetStarredMentees fallbacked to API, this is actually
				// not neeeded. Since this is a set, it doesn't matter much.
				menteeOverviewApi.starredMentees.push( Number( userId ) );
			} );
		} );
	};

	MenteeOverviewApi.prototype.unstarMentee = function ( userId ) {
		var menteeOverviewApi = this;
		return new mw.Api().postWithToken( 'csrf', {
			action: 'growthstarmentee',
			gesaction: 'unstar',
			gesmentee: '#' + userId
		} ).then( function () {
			// Do not use this.starredMentees directly, as that might not be inited yet
			return menteeOverviewApi.getStarredMentees().then( function () {
				// Remove mentee ID from list of starred mentees; In case GetStarredMentees
				// fallbacked to API, this is actually not necessary, but it shouldn't hurt.
				menteeOverviewApi.starredMentees = menteeOverviewApi.starredMentees.filter(
					function ( el ) {
						return el !== Number( userId );
					}
				);
			} );
		} );
	};

	MenteeOverviewApi.prototype.isMenteeStarred = function ( userId ) {
		return this.getStarredMentees().then( function ( mentees ) {
			return mentees.indexOf( Number( userId ) ) !== -1;
		} );
	};

	module.exports = MenteeOverviewApi;
}() );
