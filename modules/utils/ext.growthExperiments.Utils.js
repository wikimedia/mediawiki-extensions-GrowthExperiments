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
	 * @param {string} queryParam
	 *   The query param to remove from the URL.
	 */
	function removeQueryParam( url, queryParam ) {
		var newUrl;
		if ( !queryParam || !url.query[ queryParam ] ) {
			return;
		}
		delete url.query[ queryParam ];

		if ( Object.keys( url.query ).length === 1 && url.query.title ) {
			// After removing the param only title remains. Rewrite to a prettier URL.
			newUrl = mw.util.getUrl( url.query.title );
		} else {
			newUrl = url;
		}
		if ( history.replaceState ) {
			history.replaceState( history.state, document.title, newUrl.toString() );
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
	 * @return {string|null} The variant a user is assigned to.
	 */
	function getUserVariant() {
		return mw.user.options.get( 'growthexperiments-homepage-variant' );
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

	module.exports = {
		serializeActionData: serializeActionData,
		removeQueryParam: removeQueryParam,
		isValidEditor: isValidEditor,
		isUserInVariant: isUserInVariant,
		getUserVariant: getUserVariant
	};

}() );
