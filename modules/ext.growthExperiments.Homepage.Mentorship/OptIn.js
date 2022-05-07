( function () {
	var HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		);

	/**
	 * @param {Object} config Configuration object
	 * @param {Object} $container jQuery object representing the newcomer homepage container
	 */
	var attachButton = function ( config, $container ) {
		// no-op if the CTA button isn't found. This happens when the user is already
		// opted into mentorship.
		if ( !$container.find( config.buttonSelector ).length ) {
			return;
		}

		var mode = $container.find( '.growthexperiments-homepage-module-mentorship-optin' ).data( 'mode' );

		var ctaButton = OO.ui.ButtonWidget.static.infuse( $container.find( config.buttonSelector ) );
		ctaButton.on( 'click', function () {
			OO.ui.confirm(
				mw.msg( 'growthexperiments-homepage-mentorship-confirm-dialog-text' ),
				{
					title: mw.msg( 'growthexperiments-homepage-mentorship-confirm-dialog-header' ),
					actions: [
						{
							action: 'reject',
							label: mw.msg( 'growthexperiments-homepage-mentorship-confirm-dialog-cancel' ),
							flags: 'safe'
						},
						{
							action: 'accept',
							label: mw.msg( 'growthexperiments-homepage-mentorship-confirm-dialog-continue' ),
							flags: [ 'primary', 'progressive' ]
						}
					]
				}
			).then( function ( confirmed ) {
				if ( confirmed ) {
					return new mw.Api().postWithToken( 'csrf', {
						action: 'growthsetmenteestatus',
						state: 'enabled'
					} ).then( function () {
						homepageModuleLogger.log( 'mentorship', mode, 'mentorship-optin' );
						history.replaceState( null, '', mw.util.getUrl( 'Special:Homepage' ) );
						window.location.reload();
					} );
				}
			} );
		} );
	};
	module.exports = attachButton;
}() );
