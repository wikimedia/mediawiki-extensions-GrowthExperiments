/**
 * Growth Experiments Echo Notifications CTR Tracking and experiment analytics
 *
 * @since 1.42
 */
( function () {
	'use strict';
	const EchoNotificationsTracker = require( './EchoNotificationsTracker.js' );

	$( () => {
		mw.loader.using( [ 'ext.wikimediaEvents.testKitchen' ] ).then( ( require ) => {
			const { ClickThroughRateInstrument } = require( 'ext.wikimediaEvents.testKitchen' );
			EchoNotificationsTracker.start(
				ClickThroughRateInstrument,
				mw.testKitchen.getExperiment( 'growthexperiments-get-started-notification' ),
			);
		} );
	} );
}() );
