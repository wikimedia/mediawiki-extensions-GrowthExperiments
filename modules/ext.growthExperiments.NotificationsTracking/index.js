/**
 * Growth Experiments Echo Notifications CTR Tracking and experiment analytics
 *
 * @since 1.42
 */
( function () {
	'use strict';
	const EchoNotificationsTracker = require( './EchoNotificationsTracker.js' );

	$( () => {
		mw.loader.using( [ 'ext.wikimediaEvents.xLab' ] ).then( ( require ) => {
			const { ClickThroughRateInstrument } = require( 'ext.wikimediaEvents.xLab' );
			const trackingEnabled = mw.config.get( 'wgGENotificationsTrackingEnabled' );
			const newNotificationsAndExperimentEnabled = mw.config.get( 'wgGELevelingUpNewNotificationsEnabled' );
			if ( trackingEnabled && newNotificationsAndExperimentEnabled ) {
				EchoNotificationsTracker.start(
					ClickThroughRateInstrument,
					mw.xLab.getExperiment( 'growthexperiments-get-started-notification' )
				);
				// Avoid enabling tracking on the same wikis an active experiment is running as the traffic
				// from the experiment would also be part of the baseline.
				return;
			}
			if ( trackingEnabled ) {
				EchoNotificationsTracker.start(
					ClickThroughRateInstrument,
					mw.eventLog.newInstrument( 'growth-experiments-getting-started-ctr' )
				);
			}
		} );
	} );
}() );
