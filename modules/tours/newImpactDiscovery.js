( function ( gt ) {
	var newImpactDiscoveryTour = new gt.TourBuilder( {
			name: 'newimpact_discovery',
			isSinglePage: true,
			shouldLog: true
		} ),
		HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		);

	/**
	 * Update the user preference to indicate the tour has been seen.
	 */
	function markTourAsSeen() {
		new mw.Api().saveOption(
			'growthexperiments-tour-newimpact-discovery',
			'1'
		);
	}

	/**
	 * Annoyingly, the tour builder declares the 'end' button in such a way that it breaks
	 * the onClick and onClose callbacks. Set up logging via a manual onclick handler instead.
	 *
	 * This method can be passed as an onShow handler.
	 *
	 * @param {Object} guider The guider configuration object
	 */
	function setupCloseButtonLogging( guider ) {
		guider.elem.find( '.guidedtour-end-button, .guidedtour-next-button' ).click( () => {
			homepageModuleLogger.log( 'generic', 'desktop', 'newimpactdiscovery-close', { type: 'button' } );
		} );
	}

	/**
	 * @param {Object} guider The guider configuration object
	 * @param {boolean} isAlternativeClose Legacy parameter, should be ignored.
	 * @param {string} closeMethod Guider close method: 'xButton', 'escapeKey', 'clickOutside'
	 */
	function logTourCloseAndMarkAsComplete( guider, isAlternativeClose, closeMethod ) {
		var type = {
			xButton: 'close-icon',
			escapeKey: 'should-not-happen',
			clickOutside: 'outside-click'
		}[ closeMethod ];

		markTourAsSeen();
		homepageModuleLogger.log( 'generic', 'desktop', 'newimpactdiscovery-close', { type: type } );
	}

	newImpactDiscoveryTour.firstStep( {
		name: 'discovery',
		titlemsg: 'growthexperiments-tour-newimpact-discovery-title',
		description: mw.message( 'growthexperiments-tour-newimpact-discovery-description' )
			.parse(),
		attachTo: '.growthexperiments-homepage-module-new-impact h2',
		position: 'bottom',
		overlay: false,
		autoFocus: true,
		buttons: [ {
			action: 'end',
			namemsg: 'growthexperiments-tour-newimpact-discovery-response-button-okay'
		} ],
		onShow: function () {
			markTourAsSeen();
			setupCloseButtonLogging( this );
		},
		onClose: logTourCloseAndMarkAsComplete
	} );
	mw.guidedTour.launchTour( 'newimpact_discovery' );
	homepageModuleLogger.log( 'generic', 'desktop', 'newimpactdiscovery-impression' );
}( mw.guidedTour ) );
