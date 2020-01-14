'use strict';

( function () {

	function GrowthTasksApi() {}

	/**
	 * Fetch suggested edits from ApiQueryGrowthTasks.
	 * Has no side effects.
	 *
	 * @param {string[]} taskTypes List of task IDs. Required.
	 * @param {string[]} topics List of topic IDs. Optional.
	 *
	 * @return {jQuery.Promise<Object>} An abortable promise with two fields:
	 *   - count: the number of tasks available. Note this is the full count, not the
	 *     task list length (which is capped to 200).
	 *     FIXME protection status is ignored by the count.
	 *   - tasks: a list of task data objects
	 */
	GrowthTasksApi.prototype.fetchTasks = function ( taskTypes, topics ) {
		var apiParams, actionApiPromise, finalPromise,
			url = new mw.Uri( window.location.href );

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
			prop: 'info|revisions|pageimages',
			inprop: 'protection|url',
			rvprop: 'ids',
			pithumbsize: 260,
			generator: 'growthtasks',
			// Fetch more in case protected articles are in the result set, so that after
			// filtering we can have 200.
			// TODO: Filter out protected articles on the server side.
			ggtlimit: 250,
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
				return {
					title: item.title,
					// Page and revision ID can be missing on development setups where the
					// returned titles are not local, and also in edge cases where the search
					// index has not caught up with a page deletion yet.
					pageId: item.pageid || null,
					revisionId: item.revisions ? item.revisions[ 0 ].revid : null,
					url: item.canonicalurl,
					thumbnailSource: item.thumbnail && item.thumbnail.source || null,
					tasktype: item.tasktype,
					difficulty: item.difficulty,
					// empty array when no topics are selected, null with topic matching disabled,
					// otherwise an array of (topic ID, score) pairs
					topics: item.topics || null,
					maintenanceTemplates: item.maintenancetemplates || null
				};
			}
			function filterOutProtectedArticles( result ) {
				return result.protection.length === 0;
			}
			if ( data.growthtasks.totalCount > 0 ) {
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

		return finalPromise.promise( {
			abort: actionApiPromise.abort.bind( actionApiPromise )
		} );
	};

	module.exports = GrowthTasksApi;
}() );
