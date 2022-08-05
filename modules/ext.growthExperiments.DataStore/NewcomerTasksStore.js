var GrowthTasksApi = require( './GrowthTasksApi.js' ),
	aqsConfig = require( './AQSConfig.json' ),
	suggestedEditsConfig = require( './config.json' ),
	CONSTANTS = require( './constants.js' );

/**
 * Data store for managing suggested edits tasks, including the task queue and the current position
 * in the queue. For filters, see mw.libs.ge.FiltersStore.
 *
 * @class mw.libs.ge.NewcomerTasksStore
 * @mixes OO.EventEmitter
 * @param {mw.libs.ge.DataStore.store} root
 * @constructor
 */
function NewcomerTasksStore( root ) {
	OO.EventEmitter.call( this );
	// States
	var initialState = mw.config.get( 'wgGEHomepageModuleActionData-suggested-edits' ) || {};
	/** @property {mw.libs.ge.TaskData[]} taskQueue Fetched task data */
	this.taskQueue = [];
	/** @property {boolean} taskQueueLoading Whether the API request to fetch tasks is in progress */
	this.taskQueueLoading = false;
	/**
	 * @property {number} taskCount Total number of tasks that match the selected filters.
	 * This can be greater than this.taskQueue.length since the task data is lazy loaded.
	 */
	this.taskCount = initialState.taskCount;
	/**
	 * @property {number} tasksFetched Number of tasks fetched including the pre-loaded ones
	 * @see NewcomerTasksStore.setPreloadedTaskQueue()
	 */
	this.tasksFetchedCount = 0;
	/**
	 * @property {boolean} allTasksFetched Whether all tasks available in the filter set have been fetched
	 */
	this.allTasksFetched = false;
	/**
	 * @property {number} editCount Number of edits the user has made
	 */
	this.editCount = initialState.editCount;
	/** @property {mw.libs.ge.TaskData|null} preloadedFirstTask Task data outputted from server */
	this.preloadedFirstTask = null;
	/** @property {number} currentTaskIndex Zero-based index of the task shown */
	this.currentTaskIndex = 0;
	/** @property {Object} qualityGateConfig Quality gate config */
	this.qualityGateConfig = {};
	/** @property {mw.libs.ge.GrowthTasksApi} api */
	this.api = new GrowthTasksApi( {
		taskTypes: CONSTANTS.ALL_TASK_TYPES,
		defaultTaskTypes: CONSTANTS.DEFAULT_TASK_TYPES,
		suggestedEditsConfig: suggestedEditsConfig,
		aqsConfig: aqsConfig,
		isMobile: OO.ui.isMobile()
	} );
	/** @property {jQuery.Promise|null} apiPromise Promise for fetching tasks */
	this.apiPromise = null;
	/** @property {jQuery.Promise|null} apiFetchMoreTasksPromise Promise for fetching additional tasks */
	this.apiFetchMoreTasksPromise = null;
	/** @property {Object} backup Backed up state, used when cancelling filter selection */
	this.backup = null;
	/** @property {mw.libs.ge.FiltersStore} filters */
	this.filters = root.filters;
}

OO.mixinClass( NewcomerTasksStore, OO.EventEmitter );

// Getters

/**
 * Get the task queue
 *
 * @return {mw.libs.ge.TaskData[]}
 */
NewcomerTasksStore.prototype.getTaskQueue = function () {
	return this.taskQueue;
};

/**
 * Get the task count, which is either the count of the total number of tasks matching the current
 * filters (which could be greater than length of the current task queue) or the length of the task queue
 * if the former is not set
 *
 * @return {number}
 */
NewcomerTasksStore.prototype.getTaskCount = function () {
	return this.taskCount || this.taskQueue.length;
};

/**
 * Get the data for the currently selected task
 *
 * @return {mw.libs.ge.TaskData|undefined}
 */
NewcomerTasksStore.prototype.getCurrentTask = function () {
	return this.taskQueue[ this.currentTaskIndex ];
};

/**
 * Check whether the task queue is empty
 *
 * @return {boolean}
 */
NewcomerTasksStore.prototype.isTaskQueueEmpty = function () {
	return this.taskQueue.length === 0;
};

/**
 * Get the index of the selected task
 *
 * @return {number}
 */
NewcomerTasksStore.prototype.getQueuePosition = function () {
	return this.currentTaskIndex;
};

/**
 * Check whether previous navigation is possible
 *
 * @return {boolean}
 */
NewcomerTasksStore.prototype.hasPreviousTask = function () {
	return !this.taskQueueLoading && this.currentTaskIndex > 0;
};

/**
 * Check whether next navigation is possible
 *
 * @return {boolean}
 */
NewcomerTasksStore.prototype.hasNextTask = function () {
	return !this.taskQueueLoading && this.currentTaskIndex < this.taskQueue.length - 1;
};

/**
 * Check whether the end of the task queue has been reached
 *
 * @return {boolean}
 */
NewcomerTasksStore.prototype.isEndOfTaskQueue = function () {
	return this.currentTaskIndex === this.taskQueue.length - 1;
};

/**
 * Get the quality gate config
 *
 * @return {Object}
 */
NewcomerTasksStore.prototype.getQualityGateConfig = function () {
	return this.qualityGateConfig;
};

/**
 * Get the token for the current task
 *
 * @return {string}
 */
NewcomerTasksStore.prototype.getNewcomerTaskToken = function () {
	var currentTask = this.getCurrentTask();
	if ( !currentTask ) {
		throw new Error( 'Trying to get newcomertask token but there is no task' );
	}
	return currentTask.token;
};

/**
 * Get the task types to select before the user makes any selection
 *
 * @return {string[]}
 */
NewcomerTasksStore.prototype.getDefaultTaskTypes = function () {
	return this.api.defaultTaskTypes;
};

// Mutations

/**
 * Update the task queue with the specified value
 *
 * @param {mw.libs.ge.TaskData[]} taskQueue
 */
NewcomerTasksStore.prototype.setTaskQueue = function ( taskQueue ) {
	this.taskQueue = taskQueue;
	this.maybeUpdateQualityGateConfig( this.getCurrentTask() );
	this.onTaskQueueChanged();
};

/**
 * Add the specified tasks to the task queue
 *
 * @param {mw.libs.ge.TaskData[]} additionalTasks
 */
NewcomerTasksStore.prototype.addToTaskQueue = function ( additionalTasks ) {
	this.taskQueue = this.taskQueue.concat( additionalTasks );
	this.onTaskQueueChanged();
};

/**
 * Add the preloaded task to the beginning of the task queue
 *
 * @param {mw.libs.ge.TaskData} firstTask
 */
NewcomerTasksStore.prototype.setPreloadedFirstTask = function ( firstTask ) {
	this.preloadedFirstTask = firstTask;
	this.taskQueue = [ this.preloadedFirstTask ];
	this.currentTaskIndex = 0;
	this.maybeUpdateQualityGateConfig( firstTask );
};

/**
 * Add the preloaded tasks to the beginning of the task queue
 *
 * @param {mw.libs.ge.TaskData[]} taskQueue
 */
NewcomerTasksStore.prototype.setPreloadedTaskQueue = function ( taskQueue ) {
	this.setPreloadedFirstTask( taskQueue[ 0 ] );
	this.taskQueue = taskQueue.slice();
	this.tasksFetchedCount = this.taskQueue.length;
	// Assume we preloaded all available tasks in the taskset when the number
	// of tasks informed by the taskCount initial value is lesser than the api
	// pageSize
	if ( this.lessResultsThanRequested( this.taskCount ) ) {
		this.allTasksFetched = true;
		this.taskCount = this.taskQueue.length;
	}
	this.onTaskQueueChanged();
};

/**
 * Set the flag indicating whether tasks are being fetched
 *
 * @param {boolean} isLoading
 */
NewcomerTasksStore.prototype.setTaskQueueLoading = function ( isLoading ) {
	this.taskQueueLoading = isLoading;
};

// Events emitted when state changes for reactivity

/**
 * Emit an event when the task queue changes
 */
NewcomerTasksStore.prototype.onTaskQueueChanged = function () {
	this.emit( CONSTANTS.EVENTS.TASK_QUEUE_CHANGED );
};

/**
 * Emit an event when more tasks are being fetched; used to show loading states
 *
 * @param {boolean} isLoading Whether the API to fetch more tasks is in progress
 */
NewcomerTasksStore.prototype.onFetchedMoreTasks = function ( isLoading ) {
	this.emit( CONSTANTS.EVENTS.FETCHED_MORE_TASKS, isLoading );
};

/**
 * Emit an event when the extra data for the current task changes
 */
NewcomerTasksStore.prototype.onCurrentTaskExtraDataChanged = function () {
	this.emit( CONSTANTS.EVENTS.CURRENT_TASK_EXTRA_DATA_CHANGED );
};

// Actions

/**
 * Fetch tasks based on the current filter selection
 *
 * @param {string} context Context that triggers the action
 * @param {Object} [config]
 * @param {number} [config.excludePageId] Article ID to exclude, used when showing the task feed after completing a task
 * @param {boolean} [config.excludeExceededQuotaTaskTypes] Whether to filter out the tasks which its type has exceed the daily limit
 * @return {jQuery.Promise}
 */
NewcomerTasksStore.prototype.fetchTasks = function ( context, config ) {
	if ( this.apiPromise ) {
		this.apiPromise.abort();
		this.abortedPromise = true;
	}

	var promise = $.Deferred(),
		apiConfig = { context: context };

	if ( config && config.excludePageId ) {
		apiConfig.excludePageIds = [ config.excludePageId ];
	}
	this.setTaskQueueLoading( true );
	this.apiPromise = this.api.fetchTasks(
		this.filters.getTaskTypesQuery(),
		this.filters.getTopicsQuery(),
		apiConfig );

	this.apiPromise.then( function ( data ) {
		var filterByDailyTaskLimitNotExceeded = function ( task ) {
			var qualityGate = task.qualityGateConfig[ task.tasktype ];
			if ( !qualityGate ) {
				return true;
			}
			return qualityGate.dailyLimit === false;
		};

		var updatedTaskQueue = data.tasks.slice();
		if ( config && config.excludeExceededQuotaTaskTypes === true ) {
			updatedTaskQueue = updatedTaskQueue.filter( filterByDailyTaskLimitNotExceeded );
		}
		this.taskCount = data.count;
		this.allTasksFetched = false;
		this.tasksFetchedCount = data.tasks.length;
		this.currentTaskIndex = 0;
		if ( this.preloadedFirstTask ) {
			var preloadedTask = this.preloadedFirstTask;
			updatedTaskQueue = updatedTaskQueue.filter( function ( task ) {
				return task.title !== preloadedTask.title;
			} );
			updatedTaskQueue = [ preloadedTask ].concat( updatedTaskQueue );
			this.preloadedFirstTask = null;
		}
		// When the API response returns less results than requested,
		// update the taskCount to match the real number of tasks fetched
		if ( this.lessResultsThanRequested( data.count ) ) {
			this.allTasksFetched = true;
			this.taskCount = updatedTaskQueue.length;
		}

		this.setTaskQueue( updatedTaskQueue );

		if ( this.taskQueue.length ) {
			this.maybeUpdateQualityGateConfig( this.taskQueue[ 0 ] );
			this.fetchExtraDataForCurrentTask();
			this.preloadExtraDataForUpcomingTask();
		}

		this.setTaskQueueLoading( false );
		this.synchronizeExtraData();
		this.apiPromise = null;
		promise.resolve();
	}.bind( this ) ).catch( function ( error ) {
		// Don't update the loading state if the promise is aborted (the next promise is still in progress)
		if ( this.abortedPromise ) {
			this.abortedPromise = false;
		} else {
			this.setTaskQueueLoading( false );
		}
		this.apiPromise = null;
		promise.reject( error );
	}.bind( this ) );
	return promise;
};

/**
 * Select the next task in the queue and preload extra data for the upcoming task in the queue
 */
NewcomerTasksStore.prototype.showNextTask = function () {
	if ( this.currentTaskIndex > this.getTaskCount() ) {
		return;
	}
	this.currentTaskIndex += 1;
	if ( this.isEndOfTaskQueue() ) {
		this.onFetchedMoreTasks( true );
		this.fetchMoreTasks( 'suggestedEditsModule.fetchMoreTasksOnNextCard' ).then( function () {
			this.onFetchedMoreTasks( false );
		}.bind( this ) );
	}
	this.preloadExtraDataForUpcomingTask();
	this.onTaskQueueChanged();
};

/**
 * Select the previous task in the queue
 */
NewcomerTasksStore.prototype.showPreviousTask = function () {
	if ( this.currentTaskIndex === 0 ) {
		return;
	}
	this.currentTaskIndex -= 1;
	this.onTaskQueueChanged();
};

/**
 * Fetch tasks when the last task in the queue is reached and add additional tasks to the queue
 * (unless the taskCount is reached)
 *
 * @param {string} context Context that triggers the action
 * @return {jQuery.Promise}
 */
NewcomerTasksStore.prototype.fetchMoreTasks = function ( context ) {
	if ( this.apiFetchMoreTasksPromise ) {
		this.apiFetchMoreTasksPromise.abort();
	}

	if ( this.allTasksFetched ) {
		return $.Deferred().resolve().promise();
	}

	this.setTaskQueueLoading( true );
	var existingPageIds = this.taskQueue.map( function ( task ) {
			return task.pageId;
		} ) || [],
		config = { context: context },
		currentPageId = mw.config.get( 'wgArticleId' ),
		promise = $.Deferred();

	if ( currentPageId ) {
		existingPageIds.push( currentPageId );
	}

	if ( existingPageIds.length ) {
		config.excludePageIds = existingPageIds;
	}

	this.apiFetchMoreTasksPromise = this.api.fetchTasks(
		this.filters.getTaskTypesQuery(),
		this.filters.getTopicsQuery(),
		config
	);

	this.apiFetchMoreTasksPromise.done( function ( data ) {
		var newTasks = data.tasks || [];
		// accumulate the number of tasks fetched
		this.tasksFetchedCount += newTasks.length;
		// When the API response informs the last batch of tasks has been served,
		// update the taskCount to match the real number of tasks fetched
		if ( !data.hasNext ) {
			this.allTasksFetched = true;
			this.taskCount = this.tasksFetchedCount;
		}
		this.addToTaskQueue( newTasks );
		this.preloadExtraDataForUpcomingTask();
		promise.resolve();
	}.bind( this ) ).always( function () {
		this.setTaskQueueLoading( false );
	}.bind( this ) );

	return promise;
};

/**
 * Fetch extra data which is not reliably available via the action API (we use a nondeterministic
 * generator so we cannot do query continuation, plus we reorder the results so performance
 * would be unpredictable) from the PCS and AQS services.
 *
 * @param {number} taskIndex
 * @param {string} [context] Context that triggers the action
 * @return {jQuery.Promise} Promise reflecting the status of the PCS request
 *   (AQS errors are ignored). Does not return any value; instead,
 *   this.taskQueue will be updated.
 */
NewcomerTasksStore.prototype.fetchExtraDataForTaskIndex = function ( taskIndex, context ) {
	var pcsPromise,
		aqsPromise,
		preloaded,
		apiConfig = {
			context: context || 'suggestedEditsModule.getExtraDataAndUpdateQueue'
		},
		suggestedEditData = this.taskQueue[ taskIndex ],
		promise = $.Deferred();

	if ( !suggestedEditData ) {
		return $.Deferred().resolve().promise();
	}

	pcsPromise = this.api.getExtraDataFromPcs( suggestedEditData, apiConfig );
	aqsPromise = this.api.getExtraDataFromAqs( suggestedEditData, apiConfig );

	preloaded = this.preloadCardImage( suggestedEditData );
	if ( !preloaded ) {
		pcsPromise.done( function () {
			this.preloadCardImage( suggestedEditData );
		}.bind( this ) );
	}

	$.when( pcsPromise, aqsPromise ).then( function () {
		promise.resolve();
		if ( taskIndex === this.currentTaskIndex ) {
			this.onCurrentTaskExtraDataChanged();
		}
	}.bind( this ) ).catch( function () {
		// We don't need to do anything here since the page views and RESTBase
		// calls are for supplemental data; we just need to catch any exception
		// so that the card can render with the data we have from ApiQueryGrowthTasks.
	} );
	return promise;
};

/**
 * Fetch extra data for the next task in the queue
 */
NewcomerTasksStore.prototype.preloadExtraDataForUpcomingTask = function () {
	var nextTask = this.taskQueue[ this.currentTaskIndex + 1 ];
	if ( nextTask && !nextTask.extract ) {
		this.fetchExtraDataForTaskIndex( this.currentTaskIndex + 1 );
	}
};

/**
 * Fetch extra data for the current task
 *
 * @param {string} [context] Context that triggers the action
 * @return {jQuery.Promise}
 */
NewcomerTasksStore.prototype.fetchExtraDataForCurrentTask = function ( context ) {
	var currentTask = this.getCurrentTask();
	if ( currentTask && currentTask.extract ) {
		return $.Deferred().resolve();
	}
	return this.fetchExtraDataForTaskIndex( this.currentTaskIndex, context );
};

/**
 * Preload the task card image.
 *
 * @param {Object} task Task data, as returned by GrowthTasksApi.
 * @return {boolean} Whether preloading has been started.
 */
NewcomerTasksStore.prototype.preloadCardImage = function ( task ) {
	if ( task.thumbnailSource ) {
		$( '<img>' ).attr( 'src', task.thumbnailSource );
		return true;
	}
	return false;
};

/**
 * Back up the current state
 */
NewcomerTasksStore.prototype.backupState = function () {
	this.backup = {
		taskQueue: this.getTaskQueue(),
		currentTaskIndex: this.getQueuePosition(),
		taskCount: this.getTaskCount()
	};
	this.filters.backupState();
};

/**
 * Restore the backed up state
 */
NewcomerTasksStore.prototype.restoreState = function () {
	if ( !this.backup ) {
		throw new Error( 'No state backed up' );
	}
	if ( this.apiPromise ) {
		this.apiPromise.abort();
	}
	this.taskQueue = this.backup.taskQueue;
	this.currentTaskIndex = this.backup.currentTaskIndex;
	this.taskCount = this.backup.taskCount;
	this.backup = null;
	this.filters.restoreState();
};

/**
 * Update the quality config if it's included in the task data.
 *
 * The quality gate config is initially set to the value from the task preview data. When the
 * task preview data is not available, the tasks are still fetched on the client side (the no
 * suggestions found card is shown first) so the quality config should be updated accordingly.
 *
 * @param {mw.libs.ge.TaskData|undefined} taskData
 */
NewcomerTasksStore.prototype.maybeUpdateQualityGateConfig = function ( taskData ) {
	if ( taskData && taskData.qualityGateConfig ) {
		this.qualityGateConfig = taskData.qualityGateConfig;
	}
};

/**
 * Check whether the given number is lesser or equal than the GrowthTasksApi page size
 *
 * @param {number} count the number of results received
 * @return {boolean} whether the given number of results is less than the expected
 * @see GrowthTasksApi.js
 */
NewcomerTasksStore.prototype.lessResultsThanRequested = function ( count ) {
	return count <= this.api.pageSize;
};

/**
 * Update wgGEHomepageModuleActionData-suggested-edits with the latest states
 *
 * FIXME logger is getting topics and topics match mode data from extraData so the global value has
 * to be kept in sync with those in the store
 */
NewcomerTasksStore.prototype.synchronizeExtraData = function () {
	// HomepageModuleLogger adds this to the log data automatically
	var extraData = mw.config.get( 'wgGEHomepageModuleActionData-suggested-edits' );
	if ( !extraData ) {
		// when initializing the module on the client side, this is not set
		extraData = {};
		mw.config.set( 'wgGEHomepageModuleActionData-suggested-edits', extraData );
	}
	extraData.taskTypes = this.filters.getSelectedTaskTypes();
	if ( this.filters.topicsEnabled ) {
		extraData.topics = this.filters.getTopicsQuery().getTopics();
		if ( this.filters.shouldUseTopicMatchMode ) {
			extraData.topicsMatchMode = this.filters.getTopicsQuery().getTopicsMatchMode();
		}
	}
	extraData.taskCount = this.getTaskCount();
};

module.exports = NewcomerTasksStore;
