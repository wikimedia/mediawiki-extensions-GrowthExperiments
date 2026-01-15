const EditCardWidget = require( './EditCardWidget.js' ),
	EndOfQueueWidget = require( './EndOfQueueWidget.js' ),
	ErrorCardWidget = require( './ErrorCardWidget.js' ),
	NoResultsWidget = require( './NoResultsWidget.js' ),
	TaskExplanationWidget = require( './TaskExplanationWidget.js' ),
	PagerWidget = require( './PagerWidget.js' ),
	PreviousNextWidget = require( './PreviousNextWidget.js' ),
	FiltersButtonGroupWidget = require( './FiltersButtonGroupWidget.js' ),
	NewcomerTaskLogger = require( './NewcomerTaskLogger.js' ),
	SwipePane = require( '../ui-components/SwipePane.js' ),
	QualityGate = require( './QualityGate.js' ),
	ImageSuggestionInteractionLogger = require( '../ext.growthExperiments.StructuredTask/addimage/ImageSuggestionInteractionLogger.js' ),
	LinkSuggestionInteractionLogger = require( '../ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js' ),
	CONSTANTS = require( 'ext.growthExperiments.DataStore' ).CONSTANTS,
	TOPIC_MATCH_MODES = CONSTANTS.TOPIC_MATCH_MODES,
	ALL_TASK_TYPES = CONSTANTS.ALL_TASK_TYPES;

/**
 * @class mw.libs.ge.SuggestedEditsModule
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {Object} config Configuration options
 * @param {jQuery} config.$container Module container
 * @param {jQuery} config.$element SuggestedEdits widget container
 * @param {jQuery} config.$nav Navigation element (if navigation is separate from $element)
 * @param {string} config.mode Rendering mode. See constants in IDashboardModule.php
 * @param {Object} config.qualityGateConfig Quality gate configuration exported from TaskSet.php
 * @param {HomepageModuleLogger} logger
 * @param {mw.libs.ge.DataStore} rootStore
 */
function SuggestedEditsModule( config, logger, rootStore ) {
	SuggestedEditsModule.super.call( this, config );
	this.config = config;
	this.logger = logger;
	this.mode = config.mode;
	this.currentCard = null;
	this.editWidget = null;
	this.isFirstRender = true;
	this.isShowingPseudoCard = true;
	this.filtersStore = rootStore.newcomerTasks.filters;
	this.tasksStore = rootStore.newcomerTasks;
	this.filters = new FiltersButtonGroupWidget( {
		topicMatching: this.filtersStore.topicsEnabled,
		useTopicMatchMode: this.filtersStore.shouldUseTopicMatchMode,
		mode: this.mode,
	}, logger, rootStore )
		.connect( this, {
			search: 'fetchTasksAndUpdateView',
			done: 'filterSelection',
			cancel: 'restoreState',
			open: 'onFilterOpen',
		} );
	this.newcomerTaskLogger = new NewcomerTaskLogger();

	// Topic filters will be null if the user never set them.
	// It's possible that topic filters is an empty array if the user set,
	// saved, then unset the topics.
	if ( !this.filtersStore.preferences.topicFilters ) {
		this.filters.$element.find( '.topic-filter-button' )
			.append( $( '<div>' ).addClass( 'mw-pulsating-dot' ) );
	}

	this.pager = new PagerWidget();
	this.$pagerWrapper = this.$element.find( '.suggested-edits-pager' );
	if ( !this.$pagerWrapper.length ) {
		this.$pagerWrapper = $( '<div>' ).addClass( 'suggested-edits-pager' ).appendTo( this.$element );
	}

	this.previousWidget = new PreviousNextWidget( { direction: 'Previous' } )
		.connect( this, { click: 'onPreviousCard' } );
	this.nextWidget = new PreviousNextWidget( { direction: 'Next' } )
		.connect( this, { click: 'onNextCard' } );

	let $navContainer = this.$element;
	if ( this.config.$nav.length ) {
		$navContainer = this.config.$nav;
		this.setupEditWidget( $navContainer );
		this.setupQualityGateClickHandling( this.editWidget.$button );
	}
	const cardWrapperSelector = '.suggested-edits-card-wrapper',
		$cardWrapperElement = $( cardWrapperSelector );
	this.setupQualityGateClickHandling(
		$cardWrapperElement,
	);
	const $previous = $navContainer.find( '.suggested-edits-previous' );
	const $next = $navContainer.find( '.suggested-edits-next' );

	let $filtersContainer;
	if ( this.mode === 'mobile-overlay' || this.mode === 'mobile-details' ) {
		$filtersContainer = this.$element.closest( '.growthexperiments-homepage-module' )
			.find( '.growthexperiments-homepage-module-section-subheader' );
	} else {
		$filtersContainer = this.$element;
	}
	let $filters = $filtersContainer.find( '.suggested-edits-filters' );
	if ( !$filters.length ) {
		$filters = $( '<div>' ).addClass( 'suggested-edits-filters' ).appendTo( $filtersContainer );
	}

	this.$pagerWrapper.empty().append( this.pager.$element );
	$previous.empty().append( this.previousWidget.$element );
	$next.empty().append( this.nextWidget.$element );
	$filters.empty().append( this.filters.$element );

	if ( OO.ui.isMobile() ) {
		this.setupSwipeNavigation();
	}

	// React to changes to the task data in the store (the changes could be triggered by another
	// component such as StartEditingDialog)
	this.tasksStore.on( CONSTANTS.EVENTS.TASK_QUEUE_CHANGED, this.onNewcomerTasksDataChanged.bind( this ) );
	this.tasksStore.on( CONSTANTS.EVENTS.FETCHED_MORE_TASKS, ( isFetchingTasks ) => {
		if ( isFetchingTasks ) {
			this.nextWidget.setDisabled( true );
		} else {
			this.nextWidget.setDisabled( this.tasksStore.isTaskQueueEmpty() );
		}
	} );

}

OO.inheritClass( SuggestedEditsModule, OO.ui.Widget );

/**
 * Store the latest states of the task queue, called when the dialog is opened and when the task
 * queue is updated when the dialog is first shown
 */
SuggestedEditsModule.prototype.onFilterOpen = function () {
	this.tasksStore.backupState();
	this.isFilterOpen = true;
	this.filters.updateMatchCount( this.tasksStore.getTaskCount() );
};

/**
 * Set isFilterOpen to false when the filter closes; used in onNewcomerTasksDataChanged to
 * determine whether the UI should be updated immediately or deferred
 */
SuggestedEditsModule.prototype.onFilterClose = function () {
	this.isFilterOpen = false;
};

/**
 * Restore the previous states of the task queue and update the views, called when cancelling the
 * filter selection
 */
SuggestedEditsModule.prototype.restoreState = function () {
	this.onFilterClose();
	this.tasksStore.restoreState();
};

/**
 * User has clicked "Done" in the dialog after selecting filters.
 *
 * @param {jQuery.Deferred} [filtersDialogDeferred] Deferred from the filters ProcessDialog to
 * resolve when the tasks are fetched or reject if the request failed. This is used to show the
 * loading state in the filters dialog.
 *
 * FIXME: At this point, we may already have the updated task queue since an API request is made to
 * show the task count when the filter dialog is open. When that's the case, we should use the task
 * queue from the store to avoid making two API calls for the same filters.
 */
SuggestedEditsModule.prototype.filterSelection = function ( filtersDialogDeferred ) {
	this.isFirstRender = true;
	this.onFilterClose();
	const apiPromise = this.tasksStore.fetchTasks( 'suggestedEditsModule.filterSelection' );

	if ( filtersDialogDeferred ) {
		apiPromise.then( () => {
			filtersDialogDeferred.resolve();
		}, () => {
			filtersDialogDeferred.reject();
		} );
	}
};

/**
 * Fetch tasks with the current filter selection and update the UI
 *
 * @return {jQuery.Promise} Status promise. It never fails; errors are handled internally
 * by rendering an error card.
 */
SuggestedEditsModule.prototype.fetchTasksAndUpdateView = function () {
	return this.tasksStore.fetchTasks( 'suggestedEditsModule.fetchTasksAndUpdateView' ).then( () => {
		this.logger.log( 'suggested-edits', this.mode, 'se-fetch-tasks' );
		return $.Deferred().resolve().promise();
	} ).catch( ( message ) => {
		if ( message === 'abort' ) {
			// XHR abort, not a real error
			return;
		}
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'error', errorMessage: message } );
		return this.showCard( new ErrorCardWidget() );
	} );
};

/**
 * Update the pager when the task queue changes or when the user navigates through the queue
 */
SuggestedEditsModule.prototype.updatePager = function () {
	if ( this.tasksStore.isTaskQueueEmpty() ) {
		this.pager.toggle( this.currentCard instanceof EditCardWidget );
	} else {
		this.pager.setMessage( this.tasksStore.getQueuePosition() + 1, this.tasksStore.getTaskCount() );
		this.pager.toggle( true );
	}
};

/**
 * Enable/disable the previous and next buttons based on the current position and the task queue;
 * hide them if task card isn't shown
 */
SuggestedEditsModule.prototype.updatePreviousNextButtons = function () {
	const shouldShowNavigation = this.currentCard instanceof EditCardWidget || !this.tasksStore.isTaskQueueEmpty(),
		// next is enabled when the end of the queue is reached to show EndOfQueueWidget
		shouldEnableNext = this.tasksStore.taskQueueLoading ? false : this.tasksStore.hasNextTask() || this.tasksStore.isEndOfTaskQueue();
	this.previousWidget.setDisabled( !this.tasksStore.hasPreviousTask() );
	this.nextWidget.setDisabled( !shouldEnableNext );
	this.previousWidget.toggle( shouldShowNavigation );
	this.nextWidget.toggle( shouldShowNavigation );
};

/**
 * Show the next card if it's available
 *
 * @param {boolean} [isSwipe] Whether the action is triggered via swipe action
 */
SuggestedEditsModule.prototype.onNextCard = function ( isSwipe ) {
	const action = isSwipe ? 'se-task-navigation-swipe' : 'se-task-navigation';
	if ( this.currentCard instanceof EndOfQueueWidget ) {
		return;
	}
	this.isGoingBack = false;
	this.logger.log( 'suggested-edits', this.mode, action, { dir: 'next' } );
	this.tasksStore.showNextTask();
};

/**
 * Show the previous card if it's available
 *
 * @param {boolean} [isSwipe] Whether the action is triggered via swipe action
 */
SuggestedEditsModule.prototype.onPreviousCard = function ( isSwipe ) {
	const action = isSwipe ? 'se-task-navigation-swipe' : 'se-task-navigation';
	if ( this.tasksStore.getQueuePosition() === 0 ) {
		return;
	}
	this.isGoingBack = true;
	this.logger.log( 'suggested-edits', this.mode, action, { dir: 'prev' } );
	this.tasksStore.showPreviousTask();
};

/**
 * Update data from PCS and AQS services for the current card
 */
SuggestedEditsModule.prototype.updateExtraDataForCurrentCard = function () {
	this.currentCard = new EditCardWidget(
		Object.assign(
			{ extraDataLoaded: true, qualityGateConfig: this.tasksStore.getQualityGateConfig() },
			this.tasksStore.getCurrentTask(),
		),
	);
	this.updateCardElement();
};

/**
 * Update TaskExplanationWidget for the current task
 */
SuggestedEditsModule.prototype.updateTaskExplanationWidget = function () {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $explanationElement = $( '.suggested-edits-task-explanation' ),
		currentTask = this.tasksStore.getCurrentTask();
	if ( currentTask ) {
		$explanationElement.empty().append(
			new TaskExplanationWidget( {
				taskType: currentTask.tasktype,
				mode: this.mode,
			}, this.logger, ALL_TASK_TYPES ).$element,
		);
	}
	$explanationElement.toggle( !!currentTask );
};

/**
 * Update the UI when the task queue changes
 */
SuggestedEditsModule.prototype.onNewcomerTasksDataChanged = function () {
	// Only update task count in the filter dialog if it's open
	this.filters.updateMatchCount( this.tasksStore.getTaskCount() );
	if ( this.isFilterOpen ) {
		return;
	}
	this.showCard();
	this.updateControls();
};

/**
 * Log task data for a card impression or click event to the NewcomerTask EventLogging schema.
 */
SuggestedEditsModule.prototype.logCardData = function () {
	const currentTask = this.tasksStore.getCurrentTask(),
		queuePosition = this.tasksStore.getQueuePosition();
	if ( !currentTask ) {
		const errorMessage = 'No task at queue position: ' + queuePosition;
		mw.log.error( errorMessage );
		mw.errorLogger.logError( new Error( errorMessage ), 'error.growthexperiments' );
		return;
	}
	this.newcomerTaskLogger.log( currentTask, queuePosition );
};

/**
 * Set a topic match mode and store it in the user preferences
 *
 * @param {string} mode the topic match mode to set
 */
SuggestedEditsModule.prototype.setMatchModeAndSave = function ( mode ) {
	this.filters.topicFiltersDialog.topicSelector.matchModeSelector.setSelectedMode( mode );
	this.filtersStore.setTopicsMatchMode( mode );
	this.fetchTasksAndUpdateView().then( () => {
		this.filtersStore.savePreferences();
	} );
};

/**
 * Display the given card, or the current card.
 * Sets this.currentCard.
 *
 * @param {SuggestedEditCardWidget|ErrorCardWidget|NoResultsWidget|EndOfQueueWidget|null} [card]
 *   The card to show. Only used for special cards, for normal cards typically null is passed,
 *   in which case a new SuggestedEditCardWidget will be created from the data in
 *   this.taskQueue[this.queuePosition]. This might involve fetching supplemental data via
 *   the API (which is why the method returns a promise).
 * @return {jQuery.Promise} Status promise. Might fail if fully loading the card depends
 *   on external data and fetching that fails.
 */
SuggestedEditsModule.prototype.showCard = function ( card ) {
	this.currentCard = null;

	// TODO should we log something on non-card impressions?
	if ( card ) {
		this.currentCard = card;
	} else if ( this.tasksStore.isTaskQueueEmpty() ) {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'empty' } );
		this.currentCard = new NoResultsWidget( {
			topicMatching: this.filtersStore.topicsEnabled,
			topicMatchModeIsAND: this.filtersStore.topicsMatchMode === TOPIC_MATCH_MODES.AND,
			setMatchModeOr: this.setMatchModeAndSave.bind( this, TOPIC_MATCH_MODES.OR ),
		} );
	} else if ( !this.tasksStore.getCurrentTask() ) {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'end' } );
		this.currentCard = new EndOfQueueWidget( { topicMatching: this.filtersStore.topicsEnabled } );
	} else {
		this.currentCard = new EditCardWidget(
			Object.assign( { qualityGateConfig: this.tasksStore.getQualityGateConfig() }, this.tasksStore.getCurrentTask() ),
		);
	}
	if ( this.currentCard instanceof EditCardWidget ) {
		this.logCardData();
		this.logger.log(
			'suggested-edits',
			this.mode,
			'se-task-impression',
			{ newcomerTaskToken: this.tasksStore.getNewcomerTaskToken() },
		);
	}
	this.updateCardElement( OO.ui.isMobile() ).then( this.updateControls.bind( this ) );

	if ( !( this.currentCard instanceof EditCardWidget ) ) {
		this.setEditWidgetDisabled( true );
		return $.Deferred().resolve();
	}
	this.setEditWidgetDisabled( false );

	return this.tasksStore.fetchExtraDataForCurrentTask().then(
		this.updateExtraDataForCurrentCard.bind( this ),
	);
};

/**
 * Animate the current card out and the next card in
 *
 * @param {jQuery} $cardElement
 * @param {jQuery} $cardWrapper
 * @return {jQuery.Promise} Promise that resolves when the animation is done
 */
SuggestedEditsModule.prototype.animateCard = function ( $cardElement, $cardWrapper ) {
	const isGoingBack = this.isGoingBack,
		$fakeCard = $cardElement.clone(),
		$overlayContent = this.config.$container.find( '.overlay-content' ),
		deferred = $.Deferred();

	// Prevent scrolling while animation is in progress
	$overlayContent.addClass( 'is-swiping' );

	// A copy of the current card will be animated out.
	$fakeCard.addClass( [
		'suggested-edits-card-fake',
	] ).removeClass( 'suggested-edits-card' );
	$cardWrapper.append( $fakeCard );
	// The current card is positioned off screen so it can be animated in.
	$cardElement.addClass( [ 'no-transition', isGoingBack ? 'to-start' : 'to-end' ] );

	const onTransitionEnd = function () {
		$fakeCard.remove();
		$cardElement.off( 'transitionend transitioncancel', onTransitionEnd );
		deferred.resolve();
	};

	// A delay is added to make sure the fake card is shown and the real card is off screen.
	setTimeout( () => {
		$fakeCard.addClass( isGoingBack ? 'to-end' : 'to-start' );
		$cardElement.empty().append( this.currentCard.$element );
		$cardElement.on( 'transitionend transitioncancel', onTransitionEnd );
		$cardElement.removeClass( [ 'no-transition', 'to-start', 'to-end' ] );
		$overlayContent.removeClass( 'is-swiping' );
	}, 100 );

	return deferred.promise();
};

/**
 * Update the HTML for the card with whatever is in this.currentCard.
 *
 * @param {boolean} [shouldAnimateEditCard] Whether EditCardWidget should be animated
 * @return {jQuery.Promise} Promise that resolves when card element has been updated
 */
SuggestedEditsModule.prototype.updateCardElement = function ( shouldAnimateEditCard ) {
	const cardSelector = '.suggested-edits-card',
		$cardElement = $( cardSelector ),
		$cardWrapper = $cardElement.closest( '.suggested-edits-card-wrapper' ),
		isShowingEditCardWidget = this.currentCard instanceof EditCardWidget,
		deferred = $.Deferred(),
		canAnimate = shouldAnimateEditCard &&
			isShowingEditCardWidget &&
			!this.isFirstRender &&
			!this.isShowingPseudoCard;

	if ( canAnimate ) {
		this.animateCard( $cardElement, $cardWrapper ).then( () => {
			deferred.resolve();
		} );
	} else {
		this.isFirstRender = false;
		$cardElement.empty().append( this.currentCard.$element );
		deferred.resolve();
	}

	this.isShowingPseudoCard = !isShowingEditCardWidget;
	$cardWrapper
		.toggleClass( 'pseudo-card', !isShowingEditCardWidget )
		.toggleClass( 'pseudo-card-eoq', this.currentCard instanceof EndOfQueueWidget );
	this.setupClickLogging();
	this.carryOverMpoQueryParameter();
	if ( isShowingEditCardWidget ) {
		this.setupEditTypeTracking();

	}
	return deferred.promise();
};

/**
 * Allow the user to swipe left and right to navigate through the task feed
 */
SuggestedEditsModule.prototype.setupSwipeNavigation = function () {
	const router = require( 'mediawiki.router' ),
		updateBodyClass = function ( isSwipeNavigationEnabled ) {
			$( document.body ).toggleClass(
				'growthexperiments--suggestededits-swipe-navigation-enabled',
				isSwipeNavigationEnabled,
			);
		};
	this.swipeCard = new SwipePane( this.config.$element, {
		isRtl: document.documentElement.dir === 'rtl',
		isHorizontal: true,
	} );
	this.swipeCard.setToStartHandler( () => {
		this.onNextCard( true );
	} );
	this.swipeCard.setToEndHandler( () => {
		this.onPreviousCard( true );
	} );

	// Disable scrolling on the body when the overlay is shown
	updateBodyClass( true );
	router.on( 'route', ( e ) => {
		updateBodyClass( !!e.path.match( /suggested-edits/ ) );
	} );
};

/**
 * Update the control chrome around the card depending on the state of the queue and query.
 */
SuggestedEditsModule.prototype.updateControls = function () {
	this.filters.updateButtonLabelAndIcon( this.filtersStore.getTaskTypesQuery(), this.filtersStore.getTopicsQuery() );
	this.updatePager();
	this.updatePreviousNextButtons();
	this.updateTaskExplanationWidget();
};

/**
 * Log click events on the task card or on the edit button
 *
 * @param {string} action Either 'se-task-click' or 'se-edit-button-click'
 */
SuggestedEditsModule.prototype.logEditTaskClick = function ( action ) {
	const task = this.tasksStore.getCurrentTask();
	this.logCardData();
	this.logger.log( 'suggested-edits', this.mode, action, { newcomerTaskToken: task.token } );
};

/**
 * The `mpo` query parameter is used for overriding the group of Test Kitchen A/B experiments
 *
 * It needs to persist so that the override is still active when the user arrives at the article
 *
 * See also https://wikitech.wikimedia.org/wiki/Test_Kitchen/Overriding_experiment_enrollment
 */
SuggestedEditsModule.prototype.carryOverMpoQueryParameter = function () {
	const $link = this.currentCard.$element.find( '.se-card-content' );
	if ( $link.attr( 'href' ) ) {
		const url = new URL( $link.attr( 'href' ), window.location.origin );
		const existingHomepageSearchParams = new URLSearchParams( window.location.search );
		if ( existingHomepageSearchParams.has( 'mpo' ) ) {
			url.searchParams.set( 'mpo', existingHomepageSearchParams.get( 'mpo' ) );
		}
		$link.attr( 'href', url.toString() );
	}
};

/**
 * Log click events on the task card (ie. the user visiting the task page) and pass
 * tracking data so events on the task page can be connected.
 * this.currentCard is expected to contain a valid EditCardWidget.
 */
SuggestedEditsModule.prototype.setupClickLogging = function () {
	const $link = this.currentCard.$element.find( '.se-card-content' );
	const clickId = mw.config.get( 'wgGEHomepagePageviewToken' );
	let newUrl = '';
	if ( $link.attr( 'href' ) ) {
		const url = new URL( $link.attr( 'href' ), window.location.origin );
		url.searchParams.set( 'geclickid', clickId );
		url.searchParams.set( 'genewcomertasktoken', this.tasksStore.getNewcomerTaskToken() );
		url.searchParams.set( 'gesuggestededit', '1' );
		newUrl = url.toString();
	}
	$link
		.attr( 'href', newUrl )
		.on( 'click', () => {
			if ( newUrl ) {
				// Only log if this is a task card, not the skeleton loading card
				this.logEditTaskClick( 'se-task-click' );
			}
		} );
};

/**
 * Rewrite the link to contain the task type ID, for later user in guidance.
 */
SuggestedEditsModule.prototype.setupEditTypeTracking = function () {
	const $link = this.currentCard.$element.find( '.se-card-content' );
	let newUrl = '';
	if ( $link.attr( 'href' ) ) {
		const url = new URL( $link.attr( 'href' ), window.location.origin );
		url.searchParams.set( 'getasktype', this.currentCard.getTaskType() );
		newUrl = url.toString();
	}
	$link.attr( 'href', newUrl );
	if ( this.editWidget ) {
		this.editWidget.setHref( newUrl );
	}
};

/**
 * Set up quality gate click handling for an element.
 *
 * @param {jQuery} $element The element to bind click handling to.
 */
SuggestedEditsModule.prototype.setupQualityGateClickHandling = function ( $element ) {
	$element.on( 'click', () => {
		if ( this.currentCard instanceof EditCardWidget ) {
			const qualityGate = new QualityGate( {
				gates: this.currentCard.data.qualityGateIds || [],
				gateConfig: this.tasksStore.getQualityGateConfig(),
				/* eslint-disable camelcase */
				loggers: {
					'image-recommendation': new ImageSuggestionInteractionLogger( {
						is_mobile: OO.ui.isMobile(),
						active_interface: 'qualitygate_dialog',
					} ),
					'section-image-recommendation': new ImageSuggestionInteractionLogger( {
						is_mobile: OO.ui.isMobile(),
						active_interface: 'qualitygate_dialog',
					} ),
					'link-recommendation': new LinkSuggestionInteractionLogger( {
						is_mobile: OO.ui.isMobile(),
						active_interface: 'qualitygate_dialog',
					} ),
				},
				loggerMetadataOverrides: {
					newcomer_task_token: this.currentCard.data.token,
					homepage_pageview_token: mw.config.get(
						'wgGEHomepagePageviewToken',
					),
					page_id: this.currentCard.getPageId(),
					page_title: this.currentCard.getDbKey(),
				},
				/* eslint-enable camelcase */
			} );
			return qualityGate.checkAll( this.currentCard.data.tasktype );
		}
	} );
};

/**
 * Replace edit button HTML with ButtonWidget so that OOUI methods can be used
 *
 * @param {jQuery} $container
 */
SuggestedEditsModule.prototype.setupEditWidget = function ( $container ) {
	this.editWidget = new OO.ui.ButtonWidget( {
		icon: 'edit',
		label: mw.message( 'growthexperiments-homepage-suggestededits-edit-card' ).text(),
		flags: [ 'primary', 'progressive' ],
		classes: [ 'suggested-edits-footer-navigation-edit-button' ],
	} );
	const $editButton = $container.find( '.suggested-edits-footer-navigation-edit-button' );
	$editButton.empty().append( this.editWidget.$element );

	// OO.ui.mixin.ButtonElement.onClick prevents the default action when the 'click'
	// event handler is set via OOJS, use the jQuery event handling mechanism instead.
	this.editWidget.$button.on( 'click', () => {
		// The widget state needs to be checked since this click event is fired regardless.
		if ( this.editWidget.isDisabled() ) {
			return;
		}
		this.logEditTaskClick( 'se-edit-button-click' );
	} );
};

/**
 * Enable or disable editWidget (if editWidget is shown)
 *
 * @param {boolean} isDisabled
 */
SuggestedEditsModule.prototype.setEditWidgetDisabled = function ( isDisabled ) {
	if ( this.editWidget ) {
		this.editWidget.setDisabled( isDisabled );
	}
};

module.exports = SuggestedEditsModule;
