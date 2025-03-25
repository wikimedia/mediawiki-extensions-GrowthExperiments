( function ( gt ) {
	const HomepageModuleLogger = require( '../ext.growthExperiments.Homepage.Logger/index.js' ),
		tourUtils = require( './tourUtils.js' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		isSuggestedEditsActivated = mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' );

	/**
	 * Update the user preference to indicate the tour has been seen.
	 */
	function markTourAsSeen() {
		new mw.Api().saveOption(
			'growthexperiments-tour-homepage-welcome',
			'1'
		);
	}

	/**
	 * @param {Object} guider The guider configuration object
	 * @param {boolean} isAlternativeClose Legacy parameter, should be ignored.
	 * @param {string} closeMethod Guider close method: 'xButton', 'escapeKey', 'clickOutside'
	 */
	function logTourCloseAndMarkAsComplete( guider, isAlternativeClose, closeMethod ) {
		const type = {
			xButton: 'close-icon',
			escapeKey: 'should-not-happen',
			clickOutside: 'outside-click'
		}[ closeMethod ];

		markTourAsSeen();
		homepageModuleLogger.log( 'generic', 'desktop', 'welcome-close', { type: type } );
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
			homepageModuleLogger.log( 'generic', 'desktop', 'welcome-close', { type: 'button' } );
		} );
	}

	let step;
	const welcomeTour = new gt.TourBuilder( {
		name: 'homepage_welcome',
		isSinglePage: true,
		shouldLog: true
	} );
	if ( isSuggestedEditsActivated ) {
		step = welcomeTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			// TODO: Rename this message key because it's not variant C anymore
			description: mw.message( 'growthexperiments-tour-welcome-description-c' ).parse(),
			attachTo: '#pt-userpage-2:visible, .vector-user-links #p-personal, #pt-userpage:visible',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				// There is way to influence the button icon without terrible hacks,
				// so use the 'next' button which has the right icon but breaks the onclick
				// callback, and define a fake next step and use its onShow callback instead.
				action: 'next'
			} ],
			onShow: function () {
				markTourAsSeen();
				setupCloseButtonLogging( this );
			},
			onClose: logTourCloseAndMarkAsComplete
		} ) );
		welcomeTour.step( {
			name: 'fake',
			description: 'also fake',
			onShow: function () {
				mw.guidedTour.endTour();
				mw.track( 'growthexperiments.startediting', {
					// The welcome dialog doesn't belong to any module
					moduleName: 'generic',
					trigger: 'welcome'
				} );
				// cancel displaying the guider
				return true;
			}
		} );
		step.next( 'fake' );
	} else {
		welcomeTour.firstStep( tourUtils.adjustPersonalToolbarTourStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			// TODO: Rename this message key because it's not variant D anymore
			description: mw.message( 'growthexperiments-tour-welcome-description-d' ).parse(),
			attachTo: '#pt-userpage-2:visible, .vector-user-links #p-personal, #pt-userpage:visible',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				action: 'end',
				namemsg: 'growthexperiments-tour-response-button-okay'
			} ],
			onShow: function () {
				markTourAsSeen();
				setupCloseButtonLogging( this );
			},
			onClose: logTourCloseAndMarkAsComplete
		} ) );
	}
	mw.guidedTour.launchTour( 'homepage_welcome' );
	homepageModuleLogger.log( 'generic', 'desktop', 'welcome-impression' );
}( mw.guidedTour ) );
