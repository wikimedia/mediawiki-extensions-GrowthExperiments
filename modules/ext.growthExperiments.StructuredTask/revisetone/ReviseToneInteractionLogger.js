const useExperiment = require( './useExperiment.js' );

/**
 * This follows the interface of StructuredTaskLogger
 */
module.exports = class ReviseToneInteractionLogger {
	log( action, data, metadataOverride ) {
		if ( mw.testKitchen ) {
			this.logToTestKitchen( action, data, metadataOverride );
		} else {
			this.logToConsole( action, data, metadataOverride );
		}
	}

	/* eslint-disable jsdoc/require-param */
	/**
	 * @internal
	 */
	logToTestKitchen( action, data, metadataOverride ) {
		const experiment = useExperiment();
		const interactionData = {};
		if ( metadataOverride.active_interface ) {
			// eslint-disable-next-line camelcase
			interactionData.action_subtype = metadataOverride.active_interface;
		}
		experiment.send(
			action,
			interactionData,
		);
	}

	/**
	 * @internal
	 */
	logToConsole( action, data, metadataOverride ) {
		mw.log.warn( 'ReviseTone interaction:', action, data, metadataOverride );
	}
};
