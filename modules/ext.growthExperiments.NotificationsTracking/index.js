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
			EchoNotificationsTracker.start(
				ClickThroughRateInstrument,
				mw.xLab.getExperiment( 'growthexperiments-get-started-notification' )
			);
		} );
	} );
}() );
