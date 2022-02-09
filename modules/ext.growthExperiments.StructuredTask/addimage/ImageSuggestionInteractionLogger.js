'use strict';

( function () {

	var StructuredTaskLogger = require( '../StructuredTaskLogger.js' ),
		schema = '/analytics/mediawiki/structured_task/article/image_suggestion_interaction/1.1.2',
		streamName = 'mediawiki.structured_task.article.image_suggestion_interaction';

	/**
	 * Logger for /analytics/mediawiki/structured_task/article/image_suggestion_interaction schema.
	 *
	 * @param {Object} config Used for setting defaults for metadata logged with an event
	 * @param {boolean} config.is_mobile If the interaction occurred in a mobile site context.
	 * @param {string} config.active_interface The active interface associated with the event.
	 * @class mw.libs.ge.ImageSuggestionInteractionLogger
	 * @extends mw.libs.ge.StructuredTaskLogger
	 * @constructor
	 */
	function ImageSuggestionInteractionLogger( config ) {
		this.config = config;
		ImageSuggestionInteractionLogger.super.call( this, schema, streamName, config );
	}

	OO.inheritClass( ImageSuggestionInteractionLogger, StructuredTaskLogger );

	module.exports = ImageSuggestionInteractionLogger;
}() );
