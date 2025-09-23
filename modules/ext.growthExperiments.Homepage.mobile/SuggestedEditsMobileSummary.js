const TaskPreviewWidget = require( './TaskPreviewWidget.js' ),
	MobileNoTasksWidget = require( './MobileNoTasksWidget.js' );

/**
 * Mobile-summary view of the suggested edits module
 *
 * @class mw.libs.ge.SuggestedEditsMobileSummary
 * @extends OO.ui.Widget
 *
 * @constructor
 *
 * @param {Object} config
 * @param {jQuery} config.$element
 * @param {mw.libs.ge.NewcomerTaskLogger} config.newcomerTaskLogger
 * @param {mw.libs.ge.HomepageModuleLogger} config.homepageModuleLogger
 * @param {mw.libs.ge.DataStore} rootStore
 */
function SuggestedEditsMobileSummary( config, rootStore ) {
	SuggestedEditsMobileSummary.super.call( this );
	this.newcomerTaskLogger = config.newcomerTaskLogger;
	this.homepageModuleLogger = config.homepageModuleLogger;
	this.rootStore = rootStore;
	this.tasksStore = rootStore.newcomerTasks;
	this.$element = config.$element;
	this.$content = this.$element.find( '.growthexperiments-task-preview-widget, .growthexperiments-suggestededits-mobilesummary-notasks-widget' );
}

OO.inheritClass( SuggestedEditsMobileSummary, OO.ui.Widget );

/**
 * Replace the current content with the specified widget
 *
 * @param {OO.ui.Widget} contentWidget
 */
SuggestedEditsMobileSummary.prototype.replaceContent = function ( contentWidget ) {
	const $newContentElement = contentWidget.$element;
	this.$content.replaceWith( $newContentElement );
	this.$content = $newContentElement;
};

/**
 * Show the TaskPreviewWidget for the current task in the task queue
 */
SuggestedEditsMobileSummary.prototype.showPreviewForCurrentTask = function () {
	const currentTask = this.tasksStore.getCurrentTask();
	if ( !currentTask ) {
		return;
	}
	// Always hide the page views in the small card preview by default.
	currentTask.pageviews = null;
	if ( !currentTask.extract ) {
		// Avoid rendering the loading skeleton for the description when there's no extra data.
		// See SmallTaskCard.buildCard()
		currentTask.description = null;
		currentTask.thumbnailSource = null;
	}

	this.replaceContent( new TaskPreviewWidget( {
		task: currentTask,
		taskPosition: this.tasksStore.getQueuePosition() + 1,
		taskCount: this.tasksStore.getTaskCount(),
		taskTypes: this.rootStore.CONSTANTS.ALL_TASK_TYPES,
	} ) );
};

/**
 * Show the initial state of the module
 *
 * @return {jQuery.Deferred}
 */
SuggestedEditsMobileSummary.prototype.initialize = function () {
	const taskPreviewData = mw.config.get( 'homepagemodules' )[ 'suggested-edits' ][ 'task-preview' ],
		tasksStore = this.tasksStore,
		newcomerTaskLogger = this.newcomerTaskLogger,
		homepageModuleLogger = this.homepageModuleLogger,
		deferred = $.Deferred();

	if ( taskPreviewData && taskPreviewData.title ) {
		tasksStore.setPreloadedFirstTask( taskPreviewData );
		tasksStore.fetchExtraDataForCurrentTask( 'mobilesummary' ).then( () => {
			const task = tasksStore.getCurrentTask();
			newcomerTaskLogger.log( task, 0 );
			homepageModuleLogger.log(
				'suggested-edits',
				'mobile-summary',
				'se-task-impression',
				{ newcomerTaskToken: task.token },
			);
		} ).catch( ( jqXHR, textStatus, errorThrown ) => {
			// Error loading extra data for the task
			homepageModuleLogger.log(
				'suggested-edits',
				'mobile-summary',
				'se-task-pseudo-impression',
				{ type: 'error', errorMessage: textStatus + ' ' + errorThrown },
			);
		} ).always( () => {
			this.showPreviewForCurrentTask();
			deferred.resolve();
		} );

	} else if ( tasksStore.getTaskCount() === 0 ) {
		this.replaceContent( new MobileNoTasksWidget() );
		deferred.resolve();

	} else if ( taskPreviewData && taskPreviewData.error ) {
		// Error loading the task, on the server side
		homepageModuleLogger.log(
			'suggested-edits',
			'mobile-summary',
			'se-task-pseudo-impression',
			{ type: 'error', errorMessage: taskPreviewData.error },
		);
		deferred.reject();
	}

	return deferred.promise();
};

/**
 * Activate suggested edits when the user interacts with the module
 */
SuggestedEditsMobileSummary.prototype.enableSuggestedEditsActivation = function () {
	const activationSettings = { 'growthexperiments-homepage-suggestededits-activated': 1 },
		$element = this.$element;

	// Tapping on the task card should be considered enough to activate the module, with no
	// further onboarding dialogs shown.
	$element.on( 'click', function onModuleClicked() {
		new mw.Api().saveOptions( activationSettings ).then( () => {
			mw.user.options.set( activationSettings );
		} );
		// Set state to activated so that HomepageLogger uses correct value for
		// subsequent log events.
		mw.config.set( 'wgGEHomepageModuleState-suggested-edits', 'activated' );
		$element.off( 'click', onModuleClicked );
	} );
};

/**
 * Update the module based on the latest store state.
 * Show the preview card if there is a task shown or the MobileNotTasksWidget if there is no task
 * (in SuggestedEditsModule, the user can navigate past the end of the queue)
 */
SuggestedEditsMobileSummary.prototype.updateUiBasedOnState = function () {
	const currentTask = this.tasksStore.getCurrentTask();
	if ( currentTask ) {
		this.showPreviewForCurrentTask();
	} else {
		this.replaceContent( new MobileNoTasksWidget() );
	}
};

module.exports = SuggestedEditsMobileSummary;
