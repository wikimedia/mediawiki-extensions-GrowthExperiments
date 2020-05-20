'use strict';

( function () {
	/**
	 * Logger for the NewcomerTask EventLogging schema.
	 * @class mw.libs.ge.NewcomerTaskLogger
	 * @constructor
	 */
	function NewcomerTaskLogger() {
	}

	/**
	 * Log a task returned by the task API.
	 * @param {string} context The context in which this task is logged (e.g. 'homepage-impression'
	 *   means the task was displayed on the homepage; 'postedit-click' means the task was
	 *   clicked in the post-edit dialog).
	 * @param {Object} task A task object, as returned by GrowthTasksApi
	 * @param {integer} [position] The position of the task in the task queue.
	 * @return {string} A token stored under NewcomerTask.newcomer_task_token to identify this log
	 *   event. Typically used to bind it to another log event such as a homepage module action.
	 */
	NewcomerTaskLogger.prototype.log = function ( context, task, position ) {
		var data;

		if ( task.token ) {
			// already logged
			return task.token;
		}
		task.token = mw.user.generateRandomSessionId();
		data = this.getLogData( task, position );
		data.context = context;
		mw.track( 'event.NewcomerTask', data );
		return task.token;
	};

	/**
	 * Convert a task into log data.
	 * @param {Object} task A task object, as returned by GrowthTasksApi
	 * @param {integer} [position] The position of the task in the task queue.
	 * @return {Object} Log data
	 */
	NewcomerTaskLogger.prototype.getLogData = function ( task, position ) {
		/* eslint-disable camelcase */
		var logData = {
			newcomer_task_token: task.token,
			task_type: task.tasktype,
			maintenance_templates: task.maintenanceTemplates,
			revisionId: task.revisionId,
			page_id: task.pageId,
			page_title: task.title,
			has_image: !!task.thumbnailSource,
			pageviews: task.pageviews,
			ordinal_position: position || 0
		};
		if ( task.topics && task.topics.length ) {
			logData.topic = task.topics[ 0 ][ 0 ];
			logData.matchScore = task.topics[ 0 ][ 1 ];
		}
		return logData;
		/* eslint-enable camelcase */
	};

	module.exports = NewcomerTaskLogger;
}() );
