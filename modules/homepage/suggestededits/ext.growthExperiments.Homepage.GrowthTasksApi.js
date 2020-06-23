'use strict';

( function () {

	/**
	 * Code for dealing with the query+growthtasks API, and some REST APIs to fetch extra data from.
	 * This is a stateless class, like mw.Api.
	 *
	 * @class GrowthTasksApi
	 * @constructor
	 * @param {Object} config
	 * @param {Object} [config.taskTypes] The list of task types, as returned by
	 *    HomepageHooks::getTaskTypesJson. Can be omitted when the getPreferences method is not needed.
	 * @param {Object} [config.suggestedEditsConfig] An object with the values of some PHP globals,
	 *   as returned by HomepageHooks::getSuggestedEditsConfigJson. Can be omitted when the
	 *   getPreferences method and individual task data are not needed.
	 * @param {string} config.suggestedEditsConfig.GERestbaseUrl
	 * @param {Object} config.suggestedEditsConfig.GENewcomerTasksTopicFiltersPref
	 * @param {string} [config.suggestedEditsConfig.GENewcomerTasksRemoteArticleOrigin]
	 * @param {Object} config.aqsConfig Configuration for the AQS service, as returned by
	 *   HomepageHooks::getAQSConfigJson. Used by the getExtraDataFromAqs method.
	 * @param {string} config.aqsConfig.project Project domain name to get views for (might be
	 *   different from the wiki's domain in local development setups).
	 */
	function GrowthTasksApi( config ) {
		this.taskTypes = config.taskTypes;
		this.suggestedEditsConfig = config.suggestedEditsConfig || {};
		this.aqsConfig = config.aqsConfig;
	}

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
	 */
	function handleError( error, details ) {
		var message;
		if ( error === 'http' && details && details.textStatus === 'abort' ) {
			// XHR abort, not a real error
			message = null;
		} else if ( error === 'http' ) {
			// jQuery AJAX error; textStatus is AJAX status, exception is status code text
			// from server
			message = ( typeof details.exception === 'string' ) ? details.exception : details.textStatus;
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
				message = JSON.stringify( details ).substr( 0, 1000 );
			}
		}
		return $.Deferred().reject( message ).promise();
	}

	/**
	 * Fetch suggested edits from ApiQueryGrowthTasks.
	 * Has no side effects.
	 *
	 * @param {string[]} taskTypes List of task IDs.
	 * @param {string[]} [topics] List of topic IDs.
	 * @param {Object} [config] Additional configuration.
	 * @param {boolean} [config.getDescription] Include Wikidata description into the data.
	 *   This probably won't work well with a large size setting.
	 * @param {number} [config.size] Number of tasks to fetch. The returned number might be smaller
	 *   as protected pages will be filtered out.
	 * @param {number} [config.thumbnailWidth] Ideal thumbnail width. The actual width might be
	 *   smaller if the original image itself is smaller.
	 *
	 * @return {jQuery.Promise<Object>} An abortable promise with two fields:
	 *   - count: the number of tasks available. Note this is the full count, not the
	 *     task list length (which is capped to 200).
	 *     FIXME protection status is ignored by the count.
	 *   - tasks: a list of task data objects
	 */
	GrowthTasksApi.prototype.fetchTasks = function ( taskTypes, topics, config ) {
		var apiParams, actionApiPromise, finalPromise,
			self = this,
			url = new mw.Uri( window.location.href );

		config = $.extend( {
			getDescription: false,
			size: 250,
			thumbnailWidth: 260
		}, config || {} );

		if ( !taskTypes.length ) {
			// No point in doing the query if no task types are allowed.
			return $.Deferred().resolve( {
				count: 0,
				tasks: []
			} ).promise( {
				abort: function () {}
			} );
		}

		apiParams = {
			action: 'query',
			prop: 'info|revisions|pageimages' + ( config.getDescription ? '|description' : '' ),
			inprop: 'protection',
			rvprop: 'ids',
			piprop: 'name|original|thumbnail',
			pithumbsize: config.thumbnailWidth,
			generator: 'growthtasks',
			// Fetch more in case protected articles are in the result set, so that after
			// filtering we can have 200.
			// TODO: Filter out protected articles on the server side.
			ggtlimit: config.size,
			ggttasktypes: taskTypes.join( '|' ),
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLanguage' )
		};
		if ( topics && topics.length ) {
			apiParams.ggttopics = topics.join( '|' );
		}
		if ( 'debug' in url.query ) {
			apiParams.ggtdebug = 1;
		}

		actionApiPromise = new mw.Api().get( apiParams );
		finalPromise = actionApiPromise.then( function ( data ) {
			var tasks = [];

			function cleanUpData( item ) {
				return GrowthTasksApi.fixThumbnailWidth( {
					title: item.title,
					// Page and revision ID can be missing on development setups where the
					// returned titles are not local, and also in edge cases where the search
					// index has not caught up with a page deletion yet.
					pageId: item.pageid || null,
					revisionId: item.revisions ? item.revisions[ 0 ].revid : null,
					url: self.suggestedEditsConfig.GENewcomerTasksRemoteArticleOrigin ?
						self.suggestedEditsConfig.GENewcomerTasksRemoteArticleOrigin + mw.util.getUrl( item.title ) :
						null,
					thumbnailSource: item.thumbnail && item.thumbnail.source || null,
					imageWidth: item.original && item.original.width || null,
					tasktype: item.tasktype,
					difficulty: item.difficulty,
					// empty array when no topics are selected, null with topic matching disabled,
					// otherwise an array of (topic ID, score) pairs
					topics: item.topics || null,
					maintenanceTemplates: item.maintenancetemplates || null,
					// only present when config.getDescription is true
					description: item.description || null
				}, config.thumbnailWidth );
			}
			function filterOutProtectedArticles( result ) {
				return result.protection.length === 0;
			}
			if ( data.query && data.query.pages ) {
				tasks = data.query.pages
					.filter( filterOutProtectedArticles )
					.sort( function ( l, r ) {
						return l.order - r.order;
					} )
					.map( cleanUpData )
					// Maximum number of tasks in the queue is always 200.
					.slice( 0, 200 );
			}
			if ( data.growthtasks.debug && data.growthtasks.debug.searchDebugUrls ) {
				Object.keys( data.growthtasks.debug.searchDebugUrls ).forEach( function ( type ) {
					var url = data.growthtasks.debug.searchDebugUrls[ type ],
						// eslint-disable-next-line no-console
						consoleInfo = console && console.info && console.info.bind( console ) ||
							mw.log;
					consoleInfo( 'GrowthExperiments ' + type + ' query:', url );
				} );
			}
			return {
				count: data.growthtasks.totalCount,
				tasks: tasks
			};
		} );

		actionApiPromise.fail( function ( error, details ) {
			if ( error === 'http' && details && details.textStatus === 'abort' ) {
				// XHR abort, not a real error
				return;
			}
			mw.log.error( 'Fetching task suggestions failed:', error, details );
		} );

		return finalPromise.catch( handleError.bind( this ) ).promise( {
			abort: actionApiPromise.abort.bind( actionApiPromise )
		} );
	};

	/**
	 * Get lead section extracts and page images from the Page Content Service summary API.
	 * Can be customized via $wgGERestbaseUrl (by default will be assumed to be local;
	 * setting it to null will disable querying PCS make this method a no-op).
	 *
	 * @param {Object} task A task object returned by fetchTasks. It will be extended with new data:
	 *   - extract: the page summary
	 *   - thumbnailSource: URL to the thumbnail of the page image (if one exists)
	 * @param {Object} [config] Additional configuration. The object passed to fetchTasks() can be
	 *   reused here.
	 * @param {number} [config.thumbnailWidth] Ideal thumbnail width. The actual width might be
	 *   smaller if the original image itself is smaller.
	 * @return {jQuery.Promise<Object>} A promise with the task object.
	 * @see https://www.mediawiki.org/wiki/Page_Content_Service#/page/summary
	 * @see https://en.wikipedia.org/api/rest_v1/#/Page%20content/get_page_summary__title_
	 */
	GrowthTasksApi.prototype.getExtraDataFromPcs = function ( task, config ) {
		var encodedTitle,
			title = task.title,
			apiUrlBase = this.suggestedEditsConfig.GERestbaseUrl;

		config = $.extend( {
			thumbnailWidth: 260
		}, config || {} );

		// Skip if the task already has PCS data; don't fail worse then we have to
		// when RESTBase is not installed.
		if ( 'extract' in task || !apiUrlBase ) {
			return $.Deferred().resolve( task ).promise();
		}
		encodedTitle = encodeURIComponent( title.replace( / /g, '_' ) );
		return $.get( apiUrlBase + '/page/summary/' + encodedTitle ).then( function ( data ) {
			task.extract = data.extract;
			// Normally we use the thumbnail source from the action API, this is only a fallback.
			// It is used for some beta wiki configurations and local setups, and also when the
			// action API data is missing due to query+pageimages having a smaller max limit than
			// query+growthtasks.
			if ( !task.thumbnailSource && data.thumbnail ) {
				task.thumbnailSource = data.thumbnail.source;
				task.imageWidth = data.originalimage.width;
				GrowthTasksApi.fixThumbnailWidth( task, config.thumbnailWidth );
			}
			return task;
		} );
	};

	/**
	 * Get pageview data from the Analytics Query Service.
	 * Can be customized via $wgPageViewInfoWikimediaDomain (by default will use
	 * the Wikimedia instance).
	 *
	 * @param {Object} task A task object returned by fetchTasks. It will be extended with new data:
	 *   - pageviews: number of views to the task's page in the last two months
	 * @return {jQuery.Promise<Object>} A promise with extra data to extend the task object with.
	 * @see https://wikitech.wikimedia.org/wiki/Analytics/AQS/Pageviews
	 * @see https://wikimedia.org/api/rest_v1/#/Pageviews%20data/get_metrics_pageviews_per_article__project___access___agent___article___granularity___start___end_
	 */
	GrowthTasksApi.prototype.getExtraDataFromAqs = function ( task ) {
		var encodedTitle, pageviewsApiUrl, day, firstPageviewDay, lastPageviewDay,
			title = task.title;

		if ( 'pageviews' in task ) {
			// The task already has AQS data, skip.
			return $.Deferred().resolve( task ).promise();
		}

		encodedTitle = encodeURIComponent( title.replace( / /g, '_' ) );
		// Get YYYYMMDD timestamps of 2 days ago (typically the last day that has full
		// data in AQS) and 60+2 days ago, using Javascript's somewhat cumbersome date API
		day = new Date();
		day.setDate( day.getDate() - 2 );
		lastPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		day.setDate( day.getDate() - 60 );
		firstPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		pageviewsApiUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' +
			this.aqsConfig.project + '/all-access/user/' + encodedTitle + '/daily/' +
			firstPageviewDay + '/' + lastPageviewDay;

		return $.get( pageviewsApiUrl ).then( function ( data ) {
			var pageviews = 0;
			( data.items || [] ).forEach( function ( item ) {
				pageviews += item.views;
			} );
			if ( pageviews ) {
				// This will skip on 0 views. That's OK, we don't want to show that to the user.
				task.pageviews = pageviews;
			}
			return task;
		}, function () {
			// AQS returns a 404 when the page has 0 view. Even for real errors, it's
			// not worth replacing the task card with an error message just because we
			// could not put a pageview count on it.
			return task;
		} );
	};

	// This doesn't really belong to the API class conceptually, but most callers of the API
	// will need it.
	/**
	 * Get the task type preferences of the current user.
	 *
	 * @return {{taskTypes: Array<string>, topics: Array<string>}}
	 */
	GrowthTasksApi.prototype.getPreferences = function () {
		var defaultTaskTypes = [ 'copyedit', 'links' ].filter( function ( taskType ) {
				return taskType in this.taskTypes;
			}.bind( this ) ),
			savedTaskTypes = mw.user.options.get( 'growthexperiments-homepage-se-filters' ),
			savedTopics = mw.user.options.get( this.suggestedEditsConfig.GENewcomerTasksTopicFiltersPref ),
			taskTypes = savedTaskTypes ? JSON.parse( savedTaskTypes ) : defaultTaskTypes,
			topics = savedTopics ? JSON.parse( savedTopics ) : [];

		return { taskTypes: taskTypes, topics: topics };
	};

	/**
	 * Change the thumbnail URL in the task data to be at least the given width (if possible).
	 *
	 * @param {Object} task Task data, as returned by the other methods.
	 * @param {number} newWidth Desired thumbnail width.
	 * @return {Object} The task parameter (which is changed in-place).
	 */
	GrowthTasksApi.fixThumbnailWidth = function ( task, newWidth ) {
		var data;

		if ( task.thumbnailSource && task.imageWidth ) {
			data = mw.util.parseImageUrl( task.thumbnailSource );
			if ( data && data.resizeUrl && data.width < newWidth && task.imageWidth > newWidth ) {
				task.thumbnailSource = data.resizeUrl( newWidth );
			}
		}
		return task;
	};

	module.exports = GrowthTasksApi;
}() );
