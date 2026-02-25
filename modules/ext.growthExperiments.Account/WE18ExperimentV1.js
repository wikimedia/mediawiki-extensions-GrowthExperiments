module.exports = class WE18ExperimentV1 {

	enhanceUsernameInputWithNameAdjustment() {
		if ( !mw.user.isAnon() || !OO.ui.isMobile() ) {
			return;
		}

		mw.hook( 'htmlform.enhance' ).add( ( $root ) => {
			const $usernameInput = $root.find( '#wpName2' );

			$usernameInput.on( 'input', () => {
				const originalUsername = $usernameInput.val();
				const fixedUsername = this.fixUsername( originalUsername );
				$usernameInput.val( fixedUsername );
			} );
		} );
	}

	fixUsername( username ) {
		username = username.replace( /_/g, ' ' );
		// trim leading spaces, replace with trimStart() once T419142 is done
		username = username.replace( /^\s+/, '' );
		username = username.charAt( 0 ).toUpperCase() + username.slice( 1 );
		return username;
	}
};
