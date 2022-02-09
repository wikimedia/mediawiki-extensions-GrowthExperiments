'use strict';

( function () {

	var StructuredTaskLogger = require( '../StructuredTaskLogger.js' ),
		schema = '/analytics/mediawiki/structured_task/article/link_suggestion_interaction/1.4.1',
		streamName = 'mediawiki.structured_task.article.link_suggestion_interaction';

	/**
	 * Logger for /analytics/mediawiki/structured_task/article/link_suggestion_interaction schema.
	 *
	 * @param {Object} config Used for setting defaults for metadata logged with an event
	 * @param {boolean} config.is_mobile If the interaction occurred in a mobile site context.
	 * @param {string} config.active_interface The active interface associated with the event.
	 * @class mw.libs.ge.LinkSuggestionInteractionLogger
	 * @extends mw.libs.ge.StructuredTaskLogger
	 * @constructor
	 */
	function LinkSuggestionInteractionLogger( config ) {
		this.config = config;
		LinkSuggestionInteractionLogger.super.call( this, schema, streamName, config );
	}

	OO.inheritClass( LinkSuggestionInteractionLogger, StructuredTaskLogger );

	module.exports = LinkSuggestionInteractionLogger;
}() );
