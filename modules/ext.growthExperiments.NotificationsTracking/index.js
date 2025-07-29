/**
 * Growth Experiments Echo Notifications CTR Tracking
 *
 * @since 1.42
 */
( function () {
	'use strict';
	const NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT = {
		'get-started': [
			{
				name: 'primary',
				friendlyName: 'Getting Started primary link',
				selector: 'a.mw-echo-ui-notificationItemWidget[href*="source=get-started-primary-link"]'
			}
			// FIXME the secondary link should also be instrumented, however that causes a second and incorrect
			//  interaction submission of the primary link. Avoid instrumenting it until solved, see T400048#11079207.
			// {
			//  name: 'secondary',
			//  friendlyName: 'Getting Started secondary link',
			//  selector: 'a.mw-echo-ui-menuItemWidget[href*="source=get-started-secondary-link"]'
			// }
		]
	};
	const EchoNotificationsTracker = {
		/**
		 * Start the CTR instrument for Growth notifications defined in NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT.
		 *
		 * @param {Object} ctrInstrument xLab ClickThroughRateInstrument instrument or compatible
		 */
		start( ctrInstrument ) {
			// Echo fires onInitialize after opening the popup overlay AND successfully fetching the latest
			// notifications.
			mw.hook( 'ext.echo.popup.onInitialize' ).add( () => {
				// The notification HTML should be already be rendered and visible here.
				for ( const notification in NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT ) {
					this.startNotificationInternal(
						notification,
						NOTIFICATIONS_ELEMENTS_TO_INSTRUMENT[ notification ],
						ctrInstrument
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
		 * @param {Object} ctrInstrument xLab ClickThroughRateInstrument instrument or compatible
		 */
		startNotificationInternal( notificationName, elements, ctrInstrument ) {
			for ( const el of elements ) {
				const links = document.querySelectorAll( el.selector );
				const growthInstrument = mw.eventLog.newInstrument( 'growth-experiments-getting-started-ctr' );
				if ( links.length > 0 ) {
					ctrInstrument.start( el.selector, el.friendlyName, growthInstrument );
				}
			}
		}
	};

	$( () => {
		mw.loader.using( [ 'ext.wikimediaEvents.xLab' ] ).then( ( require ) => {
			const { ClickThroughRateInstrument } = require( 'ext.wikimediaEvents.xLab' );
			EchoNotificationsTracker.start( ClickThroughRateInstrument );
		} );
	} );
}() );
