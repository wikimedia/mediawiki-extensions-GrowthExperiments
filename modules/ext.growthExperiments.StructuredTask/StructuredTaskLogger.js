var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
	Utils = require( '../utils/Utils.js' );

/**
 * Logger for structured task interactions
 *
 * @param {string} schema Schema to which to log events
 * @param {string} streamName Stream name to which to submit events
 * @param {Object} config Used for setting defaults for metadata logged with an event
 * @param {boolean} config.is_mobile If the interaction occurred in a mobile site context.
 * @param {string} config.active_interface The active interface associated with the event.
 * @class mw.libs.ge.StructuredTaskLogger
 * @constructor
 */
function StructuredTaskLogger( schema, streamName, config ) {
	if ( !schema ) {
		throw new Error( 'Missing schema for StructuredTaskLogger' );
	}
	if ( !streamName ) {
		throw new Error( 'Missing stream name for StructuredTaskLogger' );
	}
	this.schema = schema;
	this.streamName = streamName;
	this.config = config;
}

/**
 * Log an event to the schema
 *
 * @param {string} action Value of the action field
 * @param {Object|Array<string>|string|number|boolean} [data] Value of the action_data field
 * @param {Object} [metadataOverride] An object with the values of any other fields. Those
 *   fields are set to some default value if omitted.
 */
StructuredTaskLogger.prototype.log = function ( action, data, metadataOverride ) {
	var event = $.extend( {
		action: action,
		/* eslint-disable camelcase */
		action_data: Utils.serializeActionData( data )
		/* eslint-enable camelcase */
	}, this.getMetadata(), metadataOverride );
	event.$schema = this.schema;
	// mw.eventLog isn't present if EventLogging isn't loaded
	if ( mw.eventLog ) {
		mw.eventLog.submit( this.streamName, event );
	}
};

/**
 * Convert data object into log data.
 *
 * @return {Object} Default logging metadata
 */
StructuredTaskLogger.prototype.getMetadata = function () {
	return $.extend( this.config, {
		/* eslint-disable camelcase */
		newcomer_task_token: suggestedEditSession.newcomerTaskToken,
		page_id: mw.config.get( 'wgArticleId' ),
		page_title: new mw.Title( mw.config.get( 'wgPageName' ) ).getPrefixedText(),
		homepage_pageview_token: suggestedEditSession.clickId
		/* eslint-enable camelcase */
	} );
};

module.exports = StructuredTaskLogger;
