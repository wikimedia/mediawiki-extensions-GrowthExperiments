'use strict';

( function () {

	/**
	 * Singleton wrapping structured task's logger
	 *
	 * This is used for classes that apply to multiple task types where the logger for the
	 * specific task type shouldn't be directly referenced.
	 *
	 * @class mw.libs.ge.SuggestionInteractionLogger
	 * @constructor
	 */
	function SuggestionInteractionLogger() {
	}

	/**
	 * Set logger instance
	 *
	 * @param {mw.libs.ge.StructuredTaskLogger} logger
	 * @static
	 */
	SuggestionInteractionLogger.initialize = function ( logger ) {
		this.loggerInstance = logger;
	};

	/**
	 * Call log method on the logger instance (if the instance has been set)
	 *
	 * @static
	 */
	SuggestionInteractionLogger.log = function () {
		if ( this.loggerInstance ) {
			this.loggerInstance.log.apply( this.loggerInstance, arguments );
		} else {
			const error = new Error(
				'SuggestionInteractionLogger.log called before logger instance is set',
			);
			mw.log.error( error );
			mw.errorLogger.logError( error );
		}
	};

	module.exports = SuggestionInteractionLogger;
}() );
