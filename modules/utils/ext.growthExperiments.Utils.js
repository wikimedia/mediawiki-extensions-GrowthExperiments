( function () {

	/**
	 * Serialize data for use with action_data event logging property.
	 *
	 * @param {Object|string|boolean|integer|Array} data
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

	module.exports = {
		serializeActionData: serializeActionData
	};

}() );
