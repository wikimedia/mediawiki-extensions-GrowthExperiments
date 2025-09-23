const NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT = {
	'get-started': [
		{
			name: 'primary',
			friendlyName: 'Getting Started primary link',
			selector: 'a.mw-echo-ui-notificationItemWidget[href*="source=get-started-primary-link"]',
		},
		{
			name: 'secondary',
			friendlyName: 'Getting Started secondary link',
			selector: 'a.mw-echo-ui-menuItemWidget[href*="source=get-started-secondary-link"]',
			trackSingleClick: true,
		},
	],
	'keep-going': [
		{
			name: 'primary',
			friendlyName: 'Keep going primary link',
			selector: 'a.mw-echo-ui-notificationItemWidget[href*="source=keep-going-primary-link"]',
		},
		{
			name: 'secondary',
			friendlyName: 'Keep going secondary link',
			selector: 'a.mw-echo-ui-menuItemWidget[href*="source=keep-going-secondary-link"]',
			trackSingleClick: true,
		},
	],
	're-engage': [
		{
			name: 'primary',
			friendlyName: 'Re-engage primary link',
			selector: 'a.mw-echo-ui-notificationItemWidget[href*="source=re-engage-primary-link"]',
		},
		{
			name: 'secondary',
			friendlyName: 'Re-engage secondary link',
			selector: 'a.mw-echo-ui-menuItemWidget[href*="source=re-engage-secondary-link"]',
			trackSingleClick: true,
		},
	],
};
const EchoNotificationsTracker = {
	/**
	 * Start the CTR instrument for Growth notifications defined in NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT.
	 *
	 * @param {Object} ctrInstrument xLab ClickThroughRateInstrument instrument
	 * @param {Object} growthInstrument xLab instrument
	 */
	start( ctrInstrument, growthInstrument ) {
		// Echo fires onInitialize after opening the popup overlay AND successfully fetching the latest
		// notifications.
		mw.hook( 'ext.echo.popup.onInitialize' ).add( () => {
			// The notification HTML should be already be rendered and visible here.
			for ( const notification in NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT ) {
				this.startNotificationInternal(
					notification,
					NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT[ notification ],
					ctrInstrument,
					growthInstrument,
				);
			}
		} );
	},
	/**
	 * Set up CTR tracking for a single notification
	 *
	 * @param {string} [notificationName] The notification prefix name used in the link "source" query param,
	 * eg: For source=get-started-primary-link, pass get-started
	 * @param {Array<Object>} [elements] Array of objects which are target instrument descriptors, see
	 * NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT.
	 * @param {Object} ctrInstrument xLab ClickThroughRateInstrument instrument
	 * @param {Object} growthInstrument xLab instrument
	 */
	startNotificationInternal( notificationName, elements, ctrInstrument, growthInstrument ) {
		for ( const el of elements ) {
			const links = document.querySelectorAll( el.selector );
			if ( links.length > 0 ) {
				ctrInstrument.start(
					el.selector,
					el.friendlyName,
					growthInstrument,
					{ trackSingleClick: el.trackSingleClick },
				);
			}
		}
	},
};

module.exports = EchoNotificationsTracker;
