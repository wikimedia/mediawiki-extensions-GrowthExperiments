( function () {

	/**
	 * Serialize data for use with action_data event logging property.
	 *
	 * @param {Object|string|boolean|number|Array} data
	 * @return {string|*}
	 */
	function serializeActionData( data ) {
		if ( !data ) {
			return '';
		}

		if ( Array.isArray( data ) ) {
			return data.join( ';' );
		}

		if ( typeof data === 'object' ) {
			return Object.keys( data )
				.map( function ( key ) {
					return key + '=' + data[ key ];
				} )
				.join( ';' );
		}

		// assume it is string or number or bool
		return data;
	}

	/**
	 * Remove a query parameter from the URL, so the user does not see ugly URLs.
	 *
	 * @param {Object} url
	 *  Object created by mw.Uri()
	 * @param {string|string[]} queryParam
	 *   The query param(s) to remove from the URL.
	 * @param {boolean} [useLiteralFragment]
	 *   Whether to keep the fragment as is (instead of encoding it)
	 */
	function removeQueryParam( url, queryParam, useLiteralFragment ) {
		var newUrl, fragment = '', queryParams;
		if ( Array.isArray( queryParam ) ) {
			queryParams = queryParam;
		} else {
			queryParams = [ queryParam ];
		}

		if ( !queryParams.length ) {
			return;
		}
		queryParams.forEach( function ( param ) {
			delete url.query[ param ];
		} );

		if ( Object.keys( url.query ).length === 1 && url.query.title ) {
			// After removing the param only title remains. Rewrite to a prettier URL.
			newUrl = mw.util.getUrl( url.query.title );
		} else {
			newUrl = url;
		}

		// mw.uri.toString encodes fragment by default.
		if ( useLiteralFragment && url.fragment ) {
			fragment = '#' + url.fragment;
			newUrl.fragment = '';
		}

		if ( history.replaceState ) {
			history.replaceState( history.state, document.title, newUrl.toString() + fragment );
		}
	}

	/**
	 * Checks whether an editor name is accepted by the EventLogging schemas used by the extension.
	 *
	 * @param {string} editor
	 * @return {boolean}
	 */
	function isValidEditor( editor ) {
		return [
			'wikitext',
			'wikitext-2017',
			'visualeditor',
			'other'
		].indexOf( editor ) >= 0;
	}

	/**
	 * Get the variant the user is assigned to, for A/B testing and gradual rollouts.
	 *
	 * @return {string}
	 */
	function getUserVariant() {
		var variant = mw.user.options.get( 'growthexperiments-homepage-variant' );
		if ( variant === null ||
			mw.config.get( 'wgGEUserVariants' ).indexOf( variant ) === -1
		) {
			variant = mw.config.get( 'wgGEDefaultUserVariant' );
		}
		return variant;
	}

	/**
	 * Set the variant the user is assigned to, for A/B testing and gradual rollouts.
	 *
	 * @private For debug/QA purposes only.
	 * @param {string|null} variant The new variant, or null to unset.
	 * @return {jQuery.Promise}
	 */
	function setUserVariant( variant ) {
		return mw.loader.using( [ 'mediawiki.util', 'mediawiki.api' ] ).then( function () {
			return new mw.Api().saveOption( 'growthexperiments-homepage-variant', variant );
		} ).then( function () {
			// Do a cache reset as a variant switch will mess up caching.
			// FIXME T278123 remove when done.
			return $.get( mw.util.getUrl( 'Special:Homepage', { resetTaskCache: 1 } ) );
		} ).then( function () {
			window.location.reload();
		} );
	}

	/**
	 * @param {string|string[]} variants
	 * @return {boolean}
	 */
	function isUserInVariant( variants ) {
		if ( typeof variants === 'string' ) {
			variants = [ variants ];
		}
		return variants.indexOf( getUserVariant() ) !== -1;
	}

	/**
	 * Format title to be used in URLs
	 *
	 * @param {string} title
	 * @return {string}
	 */
	function formatTitle( title ) {
		return encodeURIComponent( title.replace( / /g, '_' ) );
	}

	// Expose some methods for debugging.
	window.ge = window.ge || {};
	ge.utils = {
		getUserVariant: getUserVariant,
		setUserVariant: setUserVariant
	};

	module.exports = {
		serializeActionData: serializeActionData,
		removeQueryParam: removeQueryParam,
		isValidEditor: isValidEditor,
		isUserInVariant: isUserInVariant,
		getUserVariant: getUserVariant,
		formatTitle: formatTitle
	};

}() );
