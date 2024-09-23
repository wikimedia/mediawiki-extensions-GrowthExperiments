const { ref, isRef, unref, watchEffect } = require( 'vue' );

function useMWRestApi( url ) {
	const rest = new mw.Rest();
	const data = ref( null );
	const error = ref( null );

	function doFetch() {
		// reset state before fetching..
		data.value = null;
		error.value = null;
		// We use a POST because while fetching data, we may need to write updated data
		// back to the cache table in a deferred update, and that isn't allowed with GET.
		rest.post(
			// unref() unwraps potential refs
			unref( url )
		).then( ( json ) => ( data.value = json ) )
			.catch( ( errorType, err ) => {
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
