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
	 * @param {mw.libs.ge.LinkSuggestionInteractionLogger} logger
	 */
	SuggestionInteractionLogger.initialize = function ( logger ) {
		this.loggerInstance = logger;
	};

	/**
	 * Call log method on the logger instance (if the instance has been set)
	 */
	SuggestionInteractionLogger.log = function () {
		if ( this.loggerInstance ) {
			this.loggerInstance.log.apply( this.loggerInstance, arguments );
		}
	};

	module.exports = SuggestionInteractionLogger;
}() );
