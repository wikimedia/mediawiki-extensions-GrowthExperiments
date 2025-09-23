'use strict';

( function () {
	/**
	 * Logger for the NewcomerTask EventLogging schema.
	 *
	 * @class mw.libs.ge.NewcomerTaskLogger
	 * @constructor
	 */
	function NewcomerTaskLogger() {
		// avoid sonarcloud quality gate
	}

	/**
	 * Log a task returned by the task API.
	 *
	 * @param {Object} task A task object, as returned by GrowthTasksApi
	 * @param {number} [position] The position of the task in the task queue.
	 */
	NewcomerTaskLogger.prototype.log = function ( task, position ) {
		if ( task.isTaskLogged ) {
			// already logged
			return;
		}

		task.isTaskLogged = true;
		const data = this.getLogData( task, position );
		mw.track( 'event.NewcomerTask', data );
	};

	/**
	 * Convert a task into log data.
	 *
	 * @param {Object} task A task object, as returned by GrowthTasksApi
	 * @param {number} [position] The position of the task in the task queue.
	 * @return {Object} Log data
	 */
	NewcomerTaskLogger.prototype.getLogData = function ( task, position ) {
		/* eslint-disable camelcase */
		const logData = {
			newcomer_task_token: task.token,
			task_type: task.tasktype,
			maintenance_templates: [],
			revision_id: task.revisionId,
			page_id: task.pageId,
			page_title: task.title,
			has_image: !!task.thumbnailSource,
			ordinal_position: position || 0,
		};
		if ( task.pageviews || task.pageviews === 0 ) {
			// This field can be null in the task object but is required by the eventgate schema
			// to have an integer value, so conditionally add it to logData here.
			logData.pageviews = task.pageviews;
		}
		return logData;
		/* eslint-enable camelcase */
	};

	module.exports = NewcomerTaskLogger;
}() );
