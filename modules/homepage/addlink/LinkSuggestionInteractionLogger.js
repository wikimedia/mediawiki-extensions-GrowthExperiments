'use strict';

( function () {

	var suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		Utils = require( '../../utils/ext.growthExperiments.Utils.js' );

	/**
	 * Logger for /analytics/mediawiki/structured_task/article/link_suggestion_interaction schema.
	 *
	 * @param {Object} config Used for setting defaults for metadata logged with an event
	 * @param {boolean} config.is_mobile If the interaction occurred in a mobile site context.
	 * @param {string} config.active_interface The active interface associated with the event.
	 * @class mw.libs.ge.LinkSuggestionInteractionLogger
	 * @constructor
	 */
	function LinkSuggestionInteractionLogger( config ) {
		this.events = [];
		this.config = config;
	}

	/**
	 * Log an event to the structured_task/article/link_suggestion_interaction stream
	 *
	 * @param {string} action Value of the action field
	 * @param {Object|Array<string>|string|number|boolean} [data] Value of the action_data field
	 * @param {Object} [metadataOverride] An object with the values of any other fields. Those
	 *   fields are set to some default value if omitted.
	 */
	LinkSuggestionInteractionLogger.prototype.log = function ( action, data, metadataOverride ) {
		var event = $.extend( {
			action: action,
			/* eslint-disable camelcase */
			action_data: Utils.serializeActionData( data )
			/* eslint-enable camelcase */
		}, this.getMetadata(), metadataOverride );
		event.$schema = '/analytics/mediawiki/structured_task/article/link_suggestion_interaction/1.0.0';
		mw.eventLog.submit( 'mediawiki.structured_task.article.link_suggestion_interaction', event );
		this.events.push( event );
	};

	/**
	 * Convert data object into log data.
	 *
	 * @return {Object} Default logging metadata
	 */
	LinkSuggestionInteractionLogger.prototype.getMetadata = function () {
		return $.extend( this.config, {
			/* eslint-disable camelcase */
			newcomer_task_token: suggestedEditSession.newcomerTaskToken,
			page_id: mw.config.get( 'wgArticleId' ),
			page_title: new mw.Title( mw.config.get( 'wgPageName' ) ).getPrefixedText(),
			homepage_pageview_token: suggestedEditSession.clickId
			/* eslint-enable camelcase */
		} );
	};

	/**
	 * Get events sent to mw.track by the logger. Exists for testing purposes.
	 *
	 * @return {Object[]}
	 */
	LinkSuggestionInteractionLogger.prototype.getEvents = function () {
		return this.events;
	};

	module.exports = LinkSuggestionInteractionLogger;
}() );
