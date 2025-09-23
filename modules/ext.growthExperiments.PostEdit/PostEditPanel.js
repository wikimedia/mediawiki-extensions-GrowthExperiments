const SmallTaskCard = require( '../ext.growthExperiments.Homepage.SuggestedEdits/' +
	'SmallTaskCard.js' ),
	SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
	PagerWidget = require( '../ext.growthExperiments.Homepage.SuggestedEdits/PagerWidget.js' ),
	PostEditToastMessage = require( './PostEditToastMessage.js' ),
	Utils = require( '../utils/Utils.js' ),
	rootStore = require( 'ext.growthExperiments.DataStore' ),
	CONSTANTS = rootStore.CONSTANTS;

/**
 * @class mw.libs.ge.PostEditPanel
 * @property {jQuery} $mainArea the main content element passed to the CollapsibleDrawer.
 * Kept so we can update it on fetch task queue events.
 * @mixes OO.EventEmitter
 *
 * @constructor
 * @param {Object} config
 * @param {string} config.taskType Task type of the current task.
 * @param {string} config.taskState State of the current task, as in
 *   SuggestedEditSession.taskState.
 * @param {mw.libs.ge.TaskData|null} config.nextTask Data for the next suggested edit,
 *   as returned by GrowthTasksApi, or null if there are no available tasks or fetching
 *   tasks failed.
 * @param {Object} config.taskTypes Task type data, as returned by
 *   HomepageHooks::getTaskTypesJson.
 * @param {boolean} config.imageRecommendationDailyTasksExceeded If the
 *   user has exceeded their daily limit for image recommendation tasks.
 * @param {boolean} config.sectionImageRecommendationDailyTasksExceeded If the
 *   user has exceeded their daily limit for section image recommendation tasks.
 * @param {boolean} config.linkRecommendationDailyTasksExceeded If the
 *   user has exceeded their daily limit for link recommendation tasks.
 * @param {mw.libs.ge.NewcomerTaskLogger} config.newcomerTaskLogger
 * @param {mw.libs.ge.HelpPanelLogger} config.helpPanelLogger
 * @param {mw.libs.ge.NewcomerTasksStore} config.newcomerTasksStore
 */
function PostEditPanel( config ) {
	OO.EventEmitter.call( this );
	this.taskType = config.taskType;
	this.taskState = config.taskState;
	this.nextTask = config.nextTask;
	this.taskTypes = config.taskTypes;
	this.newcomerTaskLogger = config.newcomerTaskLogger;
	this.helpPanelLogger = config.helpPanelLogger;
	this.tasksStore = config.newcomerTasksStore;
	this.$taskCard = null;
	this.$mainArea = null;
	this.imageRecommendationDailyTasksExceeded = config.imageRecommendationDailyTasksExceeded;
	this.sectionImageRecommendationDailyTasksExceeded = config.sectionImageRecommendationDailyTasksExceeded;
	this.linkRecommendationDailyTasksExceeded = config.linkRecommendationDailyTasksExceeded;
	this.prevButton = new OO.ui.ButtonWidget( {
		icon: 'previous',
		classes: [ 'mw-ge-help-panel-postedit-navigation-prev' ],
	} );
	this.nextButton = new OO.ui.ButtonWidget( {
		icon: 'next',
		classes: [ 'mw-ge-help-panel-postedit-navigation-next' ],
	} );
	this.prevButton.connect( this, { click: [ 'onPrevButtonClicked' ] } );
	this.nextButton.connect( this, { click: [ 'onNextButtonClicked' ] } );
	this.pager = new PagerWidget();
	this.pager.setLoadingMessage();
	this.togglePrevNavigation( false );
	this.toggleNextNavigation( false );

	this.tasksStore.on( CONSTANTS.EVENTS.TASK_QUEUE_LOADING, ( isLoading ) => {
		if ( isLoading ) {
			return;
		}
		this.updateNavigation();
	} );
	this.tasksStore.on( CONSTANTS.EVENTS.TASK_QUEUE_CHANGED, () => {
		const currentTask = this.tasksStore.getCurrentTask();
		if ( currentTask ) {
			this.updateNextTask( currentTask );
			this.updateNavigation();
		} else {
			this.getMainArea();
		}
	} );
	this.tasksStore.on( CONSTANTS.EVENTS.CURRENT_TASK_EXTRA_DATA_CHANGED, () => {
		const currentTask = this.tasksStore.getCurrentTask();
		if ( currentTask ) {
			this.updateTask( currentTask );
		}
	} );

	this.tasksStore.on( CONSTANTS.EVENTS.FETCHED_MORE_TASKS, ( isLoading ) => {
		// Disable next navigation until more tasks are fetched or if there are no more tasks
		const isNextEnabled = !isLoading && this.tasksStore.hasNextTask();
		this.toggleNextNavigation( isNextEnabled );
		this.nextButton.setIcon( isLoading ? 'ellipsis' : 'next' );
	} );
	this.tasksStore.on( CONSTANTS.EVENTS.TASK_QUEUE_FAILED_LOADING, () => {
		this.toggleNavigation( false );
		this.getMainArea();
	} );
}

OO.initClass( PostEditPanel );
OO.mixinClass( PostEditPanel, OO.EventEmitter );

/**
 * Get the toast message widget to be displayed on top of the panel.
 *
 * @return {OO.ui.MessageWidget}
 */
PostEditPanel.prototype.getPostEditToastMessage = function () {
	const hasSavedTask = this.taskState === SuggestedEditSession.static.STATES.SAVED;

	let type;
	if ( hasSavedTask ) {
		type = mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ? 'published' : 'saved';
		if ( mw.config.get( 'wgStableRevisionId' ) &&
			mw.config.get( 'wgStableRevisionId' ) !== mw.config.get( 'wgRevisionId' )
		) {
			// FlaggedRevs wiki, the current revision needs review
			type = 'saved';
		}
	} else {
		type = 'notsaved';
	}

	// The following messages are used here:
	// * growthexperiments-help-panel-postedit-success-message-published
	// * growthexperiments-help-panel-postedit-success-message-saved
	// * growthexperiments-help-panel-postedit-success-message-notsaved
	// * growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-image-recommendation
	// * growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-link-recommendation
	// * growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-section-image-recommendation
	let messageKey = 'growthexperiments-help-panel-postedit-success-message-' + type;
	if ( this.taskType === 'image-recommendation' && this.imageRecommendationDailyTasksExceeded ) {
		messageKey = 'growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-image-recommendation';
	} else if ( this.taskType === 'section-image-recommendation' && this.sectionImageRecommendationDailyTasksExceeded ) {
		messageKey = 'growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-section-image-recommendation';
	} else if ( this.taskType === 'link-recommendation' && this.linkRecommendationDailyTasksExceeded ) {
		messageKey = 'growthexperiments-help-panel-postedit-success-message-allavailabletasksdone-link-recommendation';
	}

	return new PostEditToastMessage( {
		icon: 'check',
		type: hasSavedTask ? 'success' : 'notice',
		label: $( '<span>' ).append( mw.message( messageKey ).parse() ),
		autoHideDuration: 5000,
	} );
};

/**
 * Get the link(s) to display in the footer.
 *
 * @return {Array<jQuery>} A list of footer elements.
 */
PostEditPanel.prototype.getFooterButtons = function () {
	const footer = new OO.ui.ButtonWidget( {
		href: Utils.getSuggestedEditsFeedUrl( 'postedit-panel' ),
		label: mw.message( 'growthexperiments-help-panel-postedit-footer' ).text(),
		framed: false,
		classes: [ 'mw-ge-help-panel-postedit-footer' ],
	} );
	footer.$element.on( 'click', this.logLinkClick.bind( this, 'homepage' ) );
	return [ footer.$element ];
};

/**
 * Get the header text for the post-edit panel content
 *
 * @return {string}
 */
PostEditPanel.prototype.getHeaderText = function () {
	if ( this.taskType === 'image-recommendation' && this.imageRecommendationDailyTasksExceeded ) {
		return mw.message( 'growthexperiments-help-panel-postedit-subheader-image-recommendation' ).text();
	} else if ( this.taskType === 'section-image-recommendation' && this.sectionImageRecommendationDailyTasksExceeded ) {
		return mw.message( 'growthexperiments-help-panel-postedit-subheader-section-image-recommendation' ).text();
	} else if ( this.taskType === 'link-recommendation' && this.linkRecommendationDailyTasksExceeded ) {
		return mw.message( 'growthexperiments-help-panel-postedit-subheader-link-recommendation' ).text();
	}
	return this.taskState === SuggestedEditSession.static.STATES.SAVED ?
		mw.message( 'growthexperiments-help-panel-postedit-subheader' ).text() :
		mw.message( 'growthexperiments-help-panel-postedit-subheader-notsaved' ).text();
};

/**
 * Get the main area of the panel (the card with a subheader).
 *
 * @return {jQuery|null} The main area, a jQuery object wrapping the card element.
 *   Null if the panel should not have a main area (as no task should be displayed).
 */
PostEditPanel.prototype.getMainArea = function () {
	let $subHeader = null;
	if ( !this.$mainArea ) {
		this.$mainArea = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-main' );
	} else {
		this.$mainArea.empty();
	}

	if ( this.taskType === 'image-recommendation' && this.imageRecommendationDailyTasksExceeded ) {
		$subHeader = $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-subheader2' )
			.text( mw.message( 'growthexperiments-help-panel-postedit-subheader2-image-recommendation' ).text() );
	} else if ( this.taskType === 'section-image-recommendation' && this.sectionImageRecommendationDailyTasksExceeded ) {
		$subHeader = $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-subheader2' )
			.text( mw.message( 'growthexperiments-help-panel-postedit-subheader2-section-image-recommendation' ).text() );
	} else if ( this.taskType === 'link-recommendation' && this.linkRecommendationDailyTasksExceeded ) {
		$subHeader = $( '<div>' )
			.addClass( 'mw-ge-help-panel-postedit-subheader2' )
			.text( mw.message( 'growthexperiments-help-panel-postedit-subheader2-link-recommendation' ).text() );
	}

	if ( this.tasksStore.isTaskQueueLoading() ) {
		this.$taskCard = this.getCard();
		this.$mainArea.append( $subHeader, this.$taskCard, this.getTaskNavigation() );
		return this.$mainArea;
	} else if ( !this.nextTask ) {
		this.$taskCard = this.getFallbackCard();
		return this.$mainArea.append( this.$taskCard );
	}

	this.$taskCard = this.getCard( this.nextTask );
	this.$mainArea.append( $subHeader, this.$taskCard, this.getTaskNavigation() );
	return this.$mainArea;
};

/**
 * Create the fallback card element.
 *
 * @return {jQuery} A jQuery object wrapping the card element.
 */
PostEditPanel.prototype.getFallbackCard = function () {
	const $fallbackCard = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-fallbackCard' );

	let postEditFallbackCardIconClass, postEditFallbackCardTitleMessage, postEditFallbackCardInfoMessage;
	if ( SuggestedEditSession.static.shouldShowLevelingUpFeatures() ) {
		postEditFallbackCardIconClass = 'mw-ge-help-panel-postedit-fallbackCard-icon-levelingup';
		postEditFallbackCardTitleMessage = 'growthexperiments-help-panel-postedit-suggestededits-levelingup-title';
		postEditFallbackCardInfoMessage = 'growthexperiments-help-panel-postedit-suggestededits-levelingup-info';
	} else {
		postEditFallbackCardIconClass = 'mw-ge-help-panel-postedit-fallbackCard-icon';
		postEditFallbackCardTitleMessage = 'growthexperiments-help-panel-postedit-suggestededits-title';
		postEditFallbackCardInfoMessage = 'growthexperiments-help-panel-postedit-suggestededits-info';
	}

	$fallbackCard.append(
		// The following classes are used here:
		// * mw-ge-help-panel-postedit-fallbackCard-icon-levelingup
		// * mw-ge-help-panel-postedit-fallbackCard-icon
		$( '<div>' ).addClass( postEditFallbackCardIconClass ),
		$( '<div>' ).append(
			$( '<div>' ).addClass( 'mw-ge-help-panel-postedit-fallbackCard-header' ).text(
				// The following messages are used here:
				// * growthexperiments-help-panel-postedit-suggestededits-title
				// * growthexperiments-help-panel-postedit-suggestededits-levelingup-title
				mw.message( postEditFallbackCardTitleMessage ).text(),
			),
			$( '<div>' ).addClass( 'mw-ge-help-panel-postedit-fallbackCard-text' ).text(
				// The following messages are used here:
				// * growthexperiments-help-panel-postedit-suggestededits-info
				// * growthexperiments-help-panel-postedit-suggestededits-levelingup-info
				mw.message( postEditFallbackCardInfoMessage ).text(),
			),
		),
	);
	return $fallbackCard;
};

/**
 * Create the card element. If no task is provided the SmallTaskCard widget
 * will be shown in a loading state.
 *
 * @param {mw.libs.ge.TaskData|null} task A task object, as returned by GrowthTasksApi
 * @return {jQuery} A jQuery object wrapping the card element.
 */
PostEditPanel.prototype.getCard = function ( task ) {
	if ( !task ) {
		return new SmallTaskCard( {} ).$element;
	}

	this.newcomerTaskLogger.log( task );
	const params = {
		geclickid: this.helpPanelLogger.helpPanelSessionId,
		getasktype: task.tasktype,
		genewcomertasktoken: task.token,
		gesuggestededit: 1,
	};
	let url;
	if ( task.url ) {
		// Override for developer setups
		url = task.url;
	} else if ( task.pageId ) {
		url = new mw.Title( 'Special:Homepage/newcomertask/' + task.pageId ).getUrl( params );
	} else {
		url = new mw.Title( task.title ).getUrl( params );
	}
	// Prevents SmallTaskCard component to render the pageviews section or the loading skeleton
	task.pageviews = null;

	const taskCard = new SmallTaskCard( {
		task: task,
		taskTypes: this.taskTypes,
		taskUrl: url,
	} );
	taskCard.connect( this, { click: 'logTaskClick' } );
	return taskCard.$element;
};

/**
 * Update the task card after some task fields have been lazy-loaded.
 *
 * @param {mw.libs.ge.TaskData} task
 */
PostEditPanel.prototype.updateTask = function ( task ) {
	const $newTaskCard = this.getCard( task );
	this.$taskCard.replaceWith( $newTaskCard );
	// Reference to the DOM node has to be updated since the old one has been replaced.
	this.$taskCard = $newTaskCard;
};

/**
 * Update the pager text and navigation arrows state
 */
PostEditPanel.prototype.updateNavigation = function () {
	this.toggleNavigation( true );
	this.togglePrevNavigation( this.tasksStore.hasPreviousTask() );
	this.toggleNextNavigation( this.tasksStore.hasNextTask() );
	this.updatePager( this.tasksStore.getQueuePosition() + 1, this.tasksStore.getTaskCount() );
};

/**
 * Toggle the pager text and navigation arrows
 *
 * @param {boolean} show Make element visible, omit to toggle visibility
 */
PostEditPanel.prototype.toggleNavigation = function ( show ) {
	this.pager.toggle( show );
	this.prevButton.toggle( show );
	this.nextButton.toggle( show );
};

/**
 * Log that the panel was displayed to the user.
 * Needs to be called by the code displaying the panel.
 *
 * @param {Object} extraData
 * @param {string} extraData.savedTaskType Type of the task for which the edit was just saved.
 * @param {string} [extraData.errorMessage] Error message, only if there was a task loading
 *   error.
 * @param {Array<string>} [extraData.userTaskTypes] User's task type filter settings,
 *   only if the impression involved showing a task
 * @param {Array<string>} [extraData.userTopics] User's topic filter settings,
 *   only if the impression involved showing a task
 */
PostEditPanel.prototype.logImpression = function ( extraData ) {
	let data;

	if ( this.nextTask ) {
		data = {
			type: 'full',
			savedTaskType: extraData.savedTaskType,
			userTaskTypes: extraData.userTaskTypes,
			newcomerTaskToken: extraData.newcomerTaskToken,
		};
		if ( extraData.userTopics && extraData.userTopics.length ) {
			data.userTopics = extraData.userTopics;
		}
		this.helpPanelLogger.log( 'postedit-impression', data );
	} else if ( extraData.errorMessage ) {
		this.helpPanelLogger.log( 'postedit-impression', {
			type: 'error',
			savedTaskType: extraData.savedTaskType,
			error: extraData.errorMessage,
		} );
	} else {
		this.helpPanelLogger.log( 'postedit-impression', {
			type: 'small',
			savedTaskType: extraData.savedTaskType,
		} );
	}
};

/**
 * Log that the panel was closed.
 * Needs to be set up by the (device-dependent) wrapper code that handles displaying the panel.
 */
PostEditPanel.prototype.logClose = function () {
	this.helpPanelLogger.log( 'postedit-close', '' );
};

/**
 * Log that one of the footer buttons was clicked.
 * This is handled automatically by the class.
 *
 * @param {string} linkName Symbolic link name ('homepage' or 'edit').
 */
PostEditPanel.prototype.logLinkClick = function ( linkName ) {
	this.helpPanelLogger.log( 'postedit-link-click', linkName );
};

/**
 * Log that the task card was clicked.
 * This is handled automatically by the class.
 */
PostEditPanel.prototype.logTaskClick = function () {
	this.newcomerTaskLogger.log( this.nextTask );
	this.helpPanelLogger.log( 'postedit-task-click', { newcomerTaskToken: this.nextTask.token } );
};

/**
 * Update the task for the next suggested edit and log an impression.
 *
 * @param {mw.libs.ge.TaskData} task
 */
PostEditPanel.prototype.updateNextTask = function ( task ) {
	this.nextTask = task;
	this.updateTask( task );
};

/**
 * Navigate to next card and log events when the next button is clicked
 *
 * @fires PostEditPanel#postedit-next-task
 */
PostEditPanel.prototype.onNextButtonClicked = function () {
	this.tasksStore.showNextTask();
	this.helpPanelLogger.log( 'postedit-task-navigation', {
		dir: 'next',
		/* eslint-disable-next-line camelcase */
		navigation_type: 'click',
	} );
};

/**
 * Navigate to previous card and log events when the prev button is clicked
 *
 */
PostEditPanel.prototype.onPrevButtonClicked = function () {
	this.tasksStore.showPreviousTask();
	this.helpPanelLogger.log( 'postedit-task-navigation', {
		dir: 'prev',
		/* eslint-disable-next-line camelcase */
		navigation_type: 'click',
	} );
};

/**
 * Get the navigation element
 *
 * @return {jQuery}
 */
PostEditPanel.prototype.getTaskNavigation = function () {
	const $navigation = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-navigation' ),
		$indicator = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-navigationIndicator' ),
		$buttons = $( '<div>' ).addClass( 'mw-ge-help-panel-postedit-navigationButtons' );
	$indicator.append( this.pager.$element );
	$buttons.append( [ this.prevButton.$element, this.nextButton.$element ] );
	$navigation.append( [ $indicator, $buttons ] );
	return $navigation;
};

/**
 * Enable or disable the prev button
 *
 * @param {boolean} isPrevNavigationEnabled
 */
PostEditPanel.prototype.togglePrevNavigation = function ( isPrevNavigationEnabled ) {
	this.prevButton.setDisabled( !isPrevNavigationEnabled );
};

/**
 * Enable or disable the next button
 *
 * @param {boolean} isNextNavigationEnabled
 */
PostEditPanel.prototype.toggleNextNavigation = function ( isNextNavigationEnabled ) {
	this.nextButton.setDisabled( !isNextNavigationEnabled );
};

/**
 * Update the pager with the latest task position
 *
 * @param {number} currentTaskPosition Current position of the task shown; 1-based index
 * @param {number} totalTasks Number of tasks in the queue
 */
PostEditPanel.prototype.updatePager = function ( currentTaskPosition, totalTasks ) {
	if ( !this.pager.isVisible() ) {
		this.pager.toggle( true );
	}
	this.pager.setMessage( currentTaskPosition, totalTasks );
};

module.exports = PostEditPanel;
