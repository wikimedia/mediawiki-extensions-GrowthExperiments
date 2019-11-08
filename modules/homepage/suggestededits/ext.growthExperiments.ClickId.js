( function () {
	var newUrl,
		url = new mw.Uri( window.location.href );

	if ( url.query.geclickid ) {
		// Change the URL of all edit links to propagate the editing session ID
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-edit a, a#ca-edit, #ca-ve-edit a, a#ca-ve-edit, .mw-editsection a' ).each( function () {
			var linkUrl = new mw.Uri( $( this ).attr( 'href' ) );
			linkUrl.extend( {
				editingStatsId: url.query.geclickid,
				editingStatsOversample: 1
			} );
			$( this ).attr( 'href', linkUrl.toString() );
		} );

		// Remove geclickid from the URL
		delete url.query.geclickid;

		if ( Object.keys( url.query ).length === 1 && url.query.title ) {
			// After removing geclickid only title remains. Rewrite to a prettier URL.
			newUrl = mw.util.getUrl( url.query.title );
		} else {
			newUrl = url;
		}
		if ( history.replaceState ) {
			history.replaceState( history.state, document.title, newUrl.toString() );
		}
	}
}() );
