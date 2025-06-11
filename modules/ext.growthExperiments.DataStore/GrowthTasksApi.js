'use strict';

/**
 * Client and helper methods for the list=growthtasks API.
 * Standalone, can be included into other modules.
 * Dependencies:
 * - mediawiki.util
 * - TaskTypesAbFilter.js (for T278123)
 */
( function () {

	const { formatTitle, normalizeLabelForStats } = require( '../utils/Utils.js' );
	const TopicFilters = require( './TopicFilters.js' );
	const DEFAULT_LOOKAHEAD_SIZE = 5;

	/**
	 * @typedef {Object} mw.libs.ge.TaskData
	 * A POJO describing a newcomer task. Usually created via fetchTasks(), but sometimes exported
	 * from PHP. Task data is partially lazy-loaded, and many fields are only present for some
	 * articles or in certain setups. We differentiate between not yet loaded fields and fields
	 * which we attempted to load but were not applicable for the given task by consistent usage of
	 * undefined vs. null.
	 *
	 * @property {string} title Article title
	 * @property {string} tasktype Task type ID.
	 * @property {string} difficulty Task difficulty ('easy', 'medium' or 'hard').
	 * @property {((string|number)[][])|null} Topics the task is in. Empty array when the user
	 *   is not filtering for any topics, null with topic matching disabled entirely, otherwise
	 *   an array of (topic ID, score) pairs.
	 * @property {number|null|undefined} pageId Article page ID. Null when the article does not
	 *   exist, which is common in some test/development setups where the returned titles are not
	 *   local, and also in edge cases where the search index has not caught up with a page
	 *   deletion yet. Can be undefined due to lazy-loading.
	 * @property {number|null|undefined} revisionId ID of last revision. Null when the article does
	 *   not exist. Can be undefined due to lazy-loading.
	 * @property {string|null} url Custom article URL. This is used in some development setups
	 *   (see $wgGENewcomerTasksRemoteArticleOrigin).
	 * @property {string|null|undefined} thumbnailSource Thumbnail URL for the page image. Null when
	 *   the article has no page image. Can be undefined due to lazy-loading.
	 *   The width of the thumbnail is unspecified, it is left to the rendering code to transform it
	 *   if needed.
	 * @property {number|null|undefined} imageWidth Width of the page image (the original, not the
	 *   thumbnail). Null / undefined when thumbnailSource is.
	 * @property {string|null|undefined} description Short article description, e.g. from Wikidata.
	 *   Null when the wiki or article is not connected to Wikidata or there is no description
	 *   in the user's language, or the article does not exist, or when the GrowthTasksApi flag for
	 *   including descriptions has not been set. Can be undefined due to lazy-loading.
	 * @property {string|null|undefined} extract Article summary (from the RESTBase summary
	 *   endpoint). Null when the article does not exist. Can be undefined due to lazy-loading, or
	 *   when the wiki does not provide article summary information.
	 * @property {string|null|undefined} token Newcomer task token. A unique identifier for the user
	 *   and task for analytics purposes.
	 * @property {number|null|undefined} pageviews Approximate number of page views in the last
	 *   60 days. Null when the article does not exist, or when no views have been processed for
	 *   that article yet. Can be undefined due to lazy-loading, or when the wiki does not provide
	 *   pageview information.
	 * @property {string[]} [qualityGateIds] IDs of quality gates associated with this task type.
	 * @property {Object} [qualityGateConfig] Quality gate related configuration, see QualityGate.js
	 */

	/**
	 * Code for dealing with the query+growthtasks API, and some REST APIs to fetch extra data from.
	 * This is a stateless class, like mw.Api.
	 *
	 * @class mw.libs.ge.GrowthTasksApi
	 * @property {number|null|undefined} pageSize the number of task items to fetch on each request
	 *   to the growth tasks api. This will be set to the value defined by
	 *   config.suggestedEditsConfig.GESearchTaskSuggesterDefaultLimit
	 * @property {number} lookAheadSize the number of extra tasks to fetch to anticipate if the next
	 *   page will contain items
	 * @constructor
	 * @param {Object} config
	 * @param {Object} [config.taskTypes] The list of task types, as returned by
	 *    HomepageHooks::getTaskTypesJson. Can be omitted when the getPreferences
	 *    method is not needed.
	 * @param {Array} config.defaultTaskTypes The default task types as returned by HomepageHooks::getDefaultTaskTypes
	 * @param {Object} [config.suggestedEditsConfig] An object with the values of some PHP globals,
	 *   as returned by HomepageHooks::getSuggestedEditsConfigJson. Can be omitted when the
	 *   getPreferences method and individual task data are not needed.
	 * @param {string} config.suggestedEditsConfig.GERestbaseUrl
	 * @param {Object} config.suggestedEditsConfig.GENewcomerTasksTopicFiltersPref
	 * @param {string} [config.suggestedEditsConfig.GENewcomerTasksRemoteArticleOrigin]
	 * @param {number} config.suggestedEditsConfig.GESearchTaskSuggesterDefaultLimit
	 * @param {number|null} config.suggestedEditsConfig.GEApiQueryGrowthTasksLookaheadSize
	 * @param {Object} config.aqsConfig Configuration for the AQS service, as returned by
	 *   HomepageHooks::getAQSConfigJson. Used by the getExtraDataFromAqs method.
	 * @param {string} config.aqsConfig.project Project domain name to get views for (might be
	 *   different from the wiki's domain in local development setups).
	 * @param {boolean} config.isMobile Whether the client is on a mobile device,
	 *   used for performance instrumentation.
	 * @param {string} [config.logContext] The context in which this GrowthTasksApi is used,
	 *   used for performance instrumentation. Can be overridden by "context" passed when calling methods in this class.
	 */
	function GrowthTasksApi( config ) {
		this.taskTypes = config.taskTypes;
		this.defaultTaskTypes = config.defaultTaskTypes;
		this.suggestedEditsConfig = config.suggestedEditsConfig || {};
		this.aqsConfig = config.aqsConfig;
		this.isMobile = config.isMobile;
		this.logContext = config.logContext;
		this.thumbnailWidth = this.isMobile ? 260 : 332;
		this.pageSize = this.suggestedEditsConfig.GESearchTaskSuggesterDefaultLimit;
		this.lookAheadSize = Number.isInteger( this.suggestedEditsConfig.GEApiQueryGrowthTasksLookaheadSize ) ?
			this.suggestedEditsConfig.GEApiQueryGrowthTasksLookaheadSize : DEFAULT_LOOKAHEAD_SIZE;
	}

	/**
	 * Fetch suggested edits from ApiQueryGrowthTasks.
	 * Has no side effects.
	 *
	 * @param {string[]} taskTypes List of task IDs.
	 * @param {mw.libs.ge.TopicFilters} [topicFilters] A TopicFilters object containing a list
	 * of topic IDs and the match mode
	 * @param {Object} [config] Additional configuration.
	 * @param {boolean} [config.getDescription] Include Wikidata description into the data.
	 *   This probably won't work well with a large size setting.
	 * @param {number} [config.size] Number of tasks to fetch. The returned number might be smaller
	 *   as protected pages will be filtered out.
	 * @param {number} [config.thumbnailWidth] Ideal thumbnail width. The actual width might be
	 *   smaller if the original image itself is smaller.
	 * @param {string} [config.context] The context in which this function was
	 *   called, used for performance instrumentation. Overrides the context given in the
	 *   constructor.
	 * @param {number[]} [config.excludePageIds] List of page IDs to exclude in query performed
	 *   in ElasticSearch.
	 *
	 * @return {jQuery.Promise<{count: number, tasks: Array<mw.libs.ge.TaskData>}>} An abortable
	 *   promise with three fields:
	 *   - hasNext: whether the API has served the last batch of tasks available within
	 *     the current taskset.
	 *   - count: the number of tasks available. Note this is the full count, not the
	 *     task list length.
	 *   - tasks: a list of task data objects
	 */
	GrowthTasksApi.prototype.fetchTasks = function ( taskTypes, topicFilters, config ) {
		const startTime = mw.now(),
			self = this,
			url = new URL( window.location.href );
		const defaultConfig = {
			getDescription: false,
			size: this.pageSize,
			thumbnailWidth: this.thumbnailWidth
		};

		// Filter out undefined values
		const filteredConfig = {};
		if ( config ) {
			Object.keys( config ).forEach( ( key ) => {
				if ( config[ key ] !== undefined ) {
					filteredConfig[ key ] = config[ key ];
				}
			} );
		}
		config = Object.assign( {}, defaultConfig, filteredConfig );

		if ( !taskTypes.length ) {
			// No point in doing the query if no task types are allowed.
			return $.Deferred().resolve( {
				count: 0,
				tasks: []
			} ).promise( {
				abort: function () {}
			} );
		}

		const apiParams = {
			action: 'query',
			prop: 'info|revisions|pageimages' + ( config.getDescription ? '|description' : '' ),
			rvprop: 'ids',
			piprop: 'name|original|thumbnail',
			pithumbsize: config.thumbnailWidth,
			generator: 'growthtasks',
			ggtlimit: config.size + this.lookAheadSize,
			ggttasktypes: taskTypes,
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLanguage' )
		};
		if ( topicFilters && topicFilters.hasFilters() ) {
			apiParams.ggttopics = topicFilters.getTopics();
		}
		if ( topicFilters && topicFilters.getTopicsMatchMode() ) {
			apiParams.ggttopicsmode = topicFilters.getTopicsMatchMode();
		}
		if ( config.excludePageIds && config.excludePageIds.length ) {
			apiParams.ggtexcludepageids = config.excludePageIds;
		}
		if ( url.searchParams.has( 'debug' ) ) {
			apiParams.ggtdebug = 1;
		}

		const actionApiPromise = new mw.Api().get( apiParams );
		const finalPromise = actionApiPromise.then( ( data ) => {
			let tasks = [];

			/**
			 * @param {Object} item Single item from query API resultset
			 * @return {mw.libs.ge.TaskData}
			 */
			function cleanUpData( item ) {
				const imageOrSectionImage = item.tasktype === 'image-recommendation' ||
						item.tasktype === 'section-image-recommendation';

				const task = {
					title: item.title,
					pageId: item.pageid || null,
					revisionId: item.revisions ? item.revisions[ 0 ].revid : null,
					url: null,
					// HACK: discard thumbnails for image recommendation tasks, where we do not
					// want to show them. This is a micro-optimization to avoid fetching page image
					// data from PCR when not needed. We still fetch it from the action API, there's
					// no way to avoid that.
					thumbnailSource: imageOrSectionImage ? null :
						// There is no way to tell whether the page has no page image or it is just
						// missing due to API continuation.
						item.thumbnail && item.thumbnail.source || undefined,
					imageWidth: item.original && item.original.width || undefined,
					tasktype: item.tasktype,
					difficulty: item.difficulty,
					topics: item.topics || null,
					token: item.token,
					description: item.description,
					qualityGateIds: item.qualityGateIds || [],
					qualityGateConfig: item.qualityGateConfig || {}
				};
				self.fixThumbnailWidth( task, config.thumbnailWidth );
				self.setUrlOverride( task );
				return task;
			}
			if ( data.query && data.query.pages ) {
				tasks = data.query.pages
					.sort( ( l, r ) => l.order - r.order )
					.map( cleanUpData );
			}
			if ( data.growthtasks.debug && data.growthtasks.debug.searchDebugUrls ) {
				Object.keys( data.growthtasks.debug.searchDebugUrls ).forEach( ( type ) => {
					const debugUrl = data.growthtasks.debug.searchDebugUrls[ type ],
						// eslint-disable-next-line no-console
						consoleInfo = console && console.info && console.info.bind( console ) ||
							mw.log;
					consoleInfo( 'GrowthExperiments ' + type + ' query:', debugUrl );
				} );
			}
			self.logTiming( 'fetchTasks', startTime, config.context );
			return {
				hasNext: tasks.length > config.size,
				count: data.growthtasks.totalCount,
				tasks: tasks.slice( 0, config.size )
			};
		} );

		return finalPromise.catch( this.handleError.bind( this ) ).promise( {
			abort: actionApiPromise.abort.bind( actionApiPromise )
		} );
	};

	/**
	 * Get lead section extracts and page images from the Page Content Service summary API.
	 * Can be customized via $wgGERestbaseUrl (by default will be assumed to be local;
	 * setting it to null will disable querying PCS make this method a no-op).
	 *
	 * @param {mw.libs.ge.TaskData} task A task object returned by fetchTasks. It will be extended
	 *   with new data:
	 *   - extract: the page summary
	 *   - thumbnailSource: URL to the thumbnail of the page image (if one exists)
	 * @param {Object} [config] Additional configuration. The object passed to fetchTasks() can be
	 *   reused here.
	 * @param {number} [config.thumbnailWidth] Ideal thumbnail width. The actual width might be
	 *   smaller if the original image itself is smaller.
	 * @param {string} [config.context] The context in which this function was
	 *   called, used for performance instrumentation. Overrides the context given in the
	 *   constructor.
	 * @return {jQuery.Promise<mw.libs.ge.TaskData>} A promise with the task object.
	 * @see https://www.mediawiki.org/wiki/Page_Content_Service#/page/summary
	 * @see https://en.wikipedia.org/api/rest_v1/#/Page%20content/get_page_summary__title_
	 */
	GrowthTasksApi.prototype.getExtraDataFromPcs = function ( task, config ) {
		const self = this,
			title = task.title,
			startTime = mw.now(),
			apiUrlBase = this.suggestedEditsConfig.GERestbaseUrl;

		config = Object.assign( {
			thumbnailWidth: this.thumbnailWidth
		}, config || {} );

		if ( task.extract !== undefined ) {
			// The task already has PCS data, skip.
			return $.Deferred().resolve( task ).promise();
		} else if ( !apiUrlBase ) {
			// Don't fail worse then we have to when RESTBase is not installed.
			task.extract = null;
			task.description = task.description || null;
			task.thumbnailSource = task.thumbnailSource || null;
			task.imageWidth = task.imageWidth || null;
			return $.Deferred().resolve( task ).promise();
		}
		const encodedTitle = formatTitle( title );
		return $.get( apiUrlBase + '/page/summary/' + encodedTitle ).then( ( data ) => {
			task.extract = data.extract || null;
			task.description = data.description || null;
			// Normally we use the thumbnail source from the action API, this is only a fallback.
			// It is used for some beta wiki configurations and local setups, and also when the
			// action API data is missing due to query+pageimages having a smaller max limit than
			// query+growthtasks.
			if ( task.thumbnailSource === undefined ) {
				if ( data.thumbnail ) {
					task.thumbnailSource = data.thumbnail.source;
					task.imageWidth = data.originalimage.width;
					self.fixThumbnailWidth( task, config.thumbnailWidth );
				} else {
					task.thumbnailSource = task.imageWidth = null;
				}
			}
			self.logTiming( 'getExtraDataFromPcs', startTime, config.context );
			return task;
		} );
	};

	/**
	 * Get pageview data from the Analytics Query Service.
	 * Can be customized via $wgPageViewInfoWikimediaDomain (by default will use
	 * the Wikimedia instance).
	 *
	 * @param {mw.libs.ge.TaskData} task A task object returned by fetchTasks. It will be extended
	 *   with new data:
	 *   - pageviews: number of views to the task's page in the last two months
	 * @param {Object} [config] Additional configuration. The object passed to fetchTasks() can be
	 *   reused here.
	 * @param {string} [config.context] The context in which this function was
	 *   called, used for performance instrumentation. Overrides the context given in the
	 *   constructor.
	 * @return {jQuery.Promise<mw.libs.ge.TaskData>} A promise with extra data to extend the task
	 *   object with.
	 * @see https://wikitech.wikimedia.org/wiki/Analytics/AQS/Pageviews
	 * @see https://w.wiki/J8K
	 */
	GrowthTasksApi.prototype.getExtraDataFromAqs = function ( task, config ) {
		const self = this,
			startTime = mw.now(),
			title = task.title;

		if ( task.pageviews !== undefined ) {
			// The task already has AQS data, skip.
			return $.Deferred().resolve( task ).promise();
		} else if ( !this.aqsConfig.project ) {
			// No AQS support for this wiki
			task.pageviews = null;
			return $.Deferred().resolve( task ).promise();
		}

		const encodedTitle = formatTitle( title );
		// Get YYYYMMDD timestamps of 2 days ago (typically the last day that has full
		// data in AQS) and 60+2 days ago, using Javascript's somewhat cumbersome date API
		const day = new Date();
		day.setDate( day.getDate() - 2 );
		const lastPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		day.setDate( day.getDate() - 60 );
		const firstPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		const pageviewsApiUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' +
			this.aqsConfig.project + '/all-access/user/' + encodedTitle + '/daily/' +
			firstPageviewDay + '/' + lastPageviewDay;

		return $.get( pageviewsApiUrl ).then( ( data ) => {
			let pageviews = 0;
			( data.items || [] ).forEach( ( item ) => {
				pageviews += item.views;
			} );
			// This will skip on 0 views. That's OK, we don't want to show that to the user.
			task.pageviews = pageviews || null;
			self.logTiming( 'getExtraDataFromAqs', startTime, config.context );
			return task;
		}, () => {
			// AQS returns a 404 when the page has 0 view. Even for real errors, it's
			// not worth replacing the task card with an error message just because we
			// could not put a pageview count on it.
			task.pageviews = null;
			return task;
		} );
	};

	// This doesn't really belong to the API class conceptually, but most callers of the API
	// will need it.
	/**
	 * Get the task type and topic filter preferences of the current user.
	 *
	 * The topicFilters value will be null if the user has never set topic filters,
	 * and an empty TopicFilters object if they had previously set topic filters
	 * but currently don't have any.
	 *
	 * @return {{taskTypes: Array<string>, topicFilters: mw.libs.ge.TopicFilters|null}}
	 */
	GrowthTasksApi.prototype.getPreferences = function () {
		function preferencesToFilters( topicsPreference, topicsMatchModePreference ) {
			if ( topicsPreference === null ) {
				return null;
			}
			return new TopicFilters( {
				topics: topicsPreference,
				topicsMatchMode: topicsMatchModePreference
			} );
		}

		const topicFiltersPref = this.suggestedEditsConfig.GENewcomerTasksTopicFiltersPref,
			savedTaskTypes = mw.user.options.get( 'growthexperiments-homepage-se-filters' ),
			savedTopics = mw.user.options.get( topicFiltersPref ),
			topics = savedTopics ? JSON.parse( savedTopics ) : null,
			savedTopicsMatchMode = mw.user.options.get( 'growthexperiments-homepage-se-topic-filters-mode' ),
			topicFilters = preferencesToFilters( topics, savedTopicsMatchMode );
		let taskTypes = savedTaskTypes ? JSON.parse( savedTaskTypes ) : this.defaultTaskTypes;

		// T278123: map the two versions of link tasks to each other - FIXME remove when done
		taskTypes = require( './TaskTypesAbFilter.js' ).convertTaskTypes( taskTypes );
		// The list of valid task types can change over time, discard invalid ones.
		taskTypes = taskTypes.filter( ( taskType ) => taskType in this.taskTypes );
		return {
			taskTypes: taskTypes,
			topicFilters: topicFilters
		};
	};

	/**
	 * A rejection decorator which makes the error message for the rejection saner.
	 * Intended to be used as a catch() handler.
	 *
	 * @param {string|Error} error Error code.
	 * @param {string|Object} details Error details.
	 * @return {jQuery.Promise} A rejected promise with a single error message string.
	 *   When the message is null, the rejection was intentional (something aborted the query)
	 *   and should not be treated as an error.
	 * @see mw.Api.ajax
	 * @see jQuery.ajax
	 * @private
	 */
	GrowthTasksApi.prototype.handleError = function ( error, details ) {
		let isRealError = true;

		let message;
		if ( error === 'http' && details && details.textStatus === 'abort' ) {
			// XHR abort, not a real error
			message = details.textStatus;
			isRealError = false;
		} else if ( error === 'http' && details && details.textStatus === 'parsererror' ) {
			message = 'Failed to parse valid JSON response';
		} else if ( error === 'http' ) {
			// jQuery AJAX error; textStatus is AJAX status, exception is status code text
			// from server (empty string for network error and non 2xx/304 for HTTP/2)
			message = details.exception === '' ?
				'HTTP error' :
				details.exception || details.textStatus;
		} else if ( error === 'ok-but-empty' ) {
			// maybe a PHP fatal; not much we can do
			message = error;
		} else if ( error instanceof Error ) {
			// JS error in our own code
			message = error.name + ': ' + error.message;
		} else {
			// API error code
			message = error;
			// DEBUG T238945
			if ( !error ) {
				// log a snippet from the API response. Errors are at the front so
				// hopefully this will show what's going on.
				message = JSON.stringify( details ).slice( 0, 1000 );
			}
		}

		if ( isRealError ) {
			mw.log.error( 'Fetching task suggestions failed: ' + message, error, details );
			mw.errorLogger.logError( error instanceof Error ? error : new Error( message ), 'error.growthexperiments' );
		}

		return $.Deferred().reject( message ).promise();
	};

	/**
	 * Set the url field on the passed task data, if article URL overrides are configured.
	 *
	 * @param {mw.libs.ge.TaskData} task
	 * @private
	 */
	GrowthTasksApi.prototype.setUrlOverride = function ( task ) {
		if ( this.suggestedEditsConfig.GENewcomerTasksRemoteArticleOrigin ) {
			task.url = this.suggestedEditsConfig.GENewcomerTasksRemoteArticleOrigin +
				mw.util.getUrl( task.title );
		}
	};

	/**
	 * Log time spent since startTime to Statsd and Prometheus.
	 *
	 * @param {string} name Name of the thing (e.g. method call) that's timed.
	 * @param {number} startTime Start timestamp, e.g. from mw.now().
	 * @param {string} [contextOverride] Overrides the context provided in the constructor.
	 * @private
	 */
	GrowthTasksApi.prototype.logTiming = function ( name, startTime, contextOverride ) {
		const duration = mw.now() - startTime;
		const platform = this.isMobile ? 'mobile' : 'desktop';
		const originalContext = contextOverride || this.logContext || 'unknown_context';

		mw.track(
			'stats.mediawiki_GrowthExperiments_special_homepage_seconds',
			duration,
			{
				module: 'growthTasksApi',
				operation: name,
				context: normalizeLabelForStats( originalContext ),
				platform: platform,
				wiki: mw.config.get( 'wgDBname' )
			}
		);
	};

	/**
	 * Change the thumbnail URL in the task data to be at least the given width (if possible).
	 *
	 * @param {mw.libs.ge.TaskData} task Task data, as returned by the other methods.
	 * @param {number} newWidth Desired thumbnail width.
	 * @private
	 */
	GrowthTasksApi.prototype.fixThumbnailWidth = function ( task, newWidth ) {
		if ( task.thumbnailSource && task.imageWidth ) {
			const data = mw.util.parseImageUrl( task.thumbnailSource );
			if ( data && data.resizeUrl && data.width < newWidth && task.imageWidth > newWidth ) {
				task.thumbnailSource = data.resizeUrl( newWidth );
			}
		}
	};

	module.exports = GrowthTasksApi;
}() );
