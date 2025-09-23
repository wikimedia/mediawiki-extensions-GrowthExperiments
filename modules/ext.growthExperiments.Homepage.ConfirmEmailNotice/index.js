( function () {
	mw.notify(
		mw.msg( 'confirmemail_loggedin' ),
		{
			// HACK: Not one of the supported types, but let's us add a class we can target
			type: 'ge-homepage-confirmemail',
		},
	);
}() );
