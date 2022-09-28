const { ref, isRef, unref, watchEffect } = require( 'vue' );

function useMWRestApi( url ) {
	const rest = new mw.Rest();
	const data = ref( null );
	const error = ref( null );

	function doFetch() {
		// reset state before fetching..
		data.value = null;
		error.value = null;
		// unref() unwraps potential refs
		rest.get( unref( url ) )
			.then( ( json ) => ( data.value = json ) )
			.catch( ( err ) => {
				// TODO: parse/inspect error response
				error.value = err;
			} );
	}

	if ( isRef( url ) ) {
		// setup reactive re-fetch if input URL is a ref
		watchEffect( doFetch );
	} else {
		// otherwise, just fetch once
		// and avoid the overhead of a watcher
		doFetch();
	}

	return { data, error };
}

module.exports = exports = useMWRestApi;
