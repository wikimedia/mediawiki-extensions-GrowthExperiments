var EditCardWidget = require( './EditCardWidget.js' ),
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
	ALL_TASK_TYPES = CONSTANTS.ALL_TASK_TYPES;

/**
 * @class
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
	var $previous, $next, $filters, $filtersContainer, $navContainer;
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
		mode: this.mode
	}, logger, rootStore )
		.connect( this, {
			search: 'fetchTasksAndUpdateView',
			done: 'filterSelection',
			cancel: 'restoreState',
			open: 'onFilterOpen'
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

	$navContainer = this.$element;
	if ( this.config.$nav.length ) {
		$navContainer = this.config.$nav;
		this.setupEditWidget( $navContainer );
		this.setupQualityGateClickHandling( this.editWidget.$button );
	}
	var cardWrapperSelector = '.suggested-edits-card-wrapper',
		$cardWrapperElement = $( cardWrapperSelector );
	this.setupQualityGateClickHandling(
		$cardWrapperElement
	);
	$previous = $navContainer.find( '.suggested-edits-previous' );
	$next = $navContainer.find( '.suggested-edits-next' );

	if ( this.mode === 'mobile-overlay' || this.mode === 'mobile-details' ) {
		$filtersContainer = this.$element.closest( '.growthexperiments-homepage-module' )
			.find( '.growthexperiments-homepage-module-section-subheader' );
	} else {
		$filtersContainer = this.$element;
	}
	$filters = $filtersContainer.find( '.suggested-edits-filters' );
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
	this.tasksStore.on( CONSTANTS.EVENTS.FETCHED_MORE_TASKS, function ( isFetchingTasks ) {
		if ( isFetchingTasks ) {
			// Ellipsis is the stand-in for a loading indicator.
			this.nextWidget.setIcon( 'ellipsis' );
			this.nextWidget.setDisabled( true );
		} else {
			this.nextWidget.setDisabled( this.tasksStore.isTaskQueueEmpty() );
			this.nextWidget.setIcon( 'arrowNext' );
		}
	}.bind( this ) );

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
 * Update the mobile summary state on Special:Homepage
 * with a small card with the first task in the queue. If there
 * are no tasks show the number of edits in the last day
 *
 * FIXME: Update mobile module to read from the same data store so that it can update itself when
 * the data changes
 */
SuggestedEditsModule.prototype.updateMobileSummary = function () {
	mw.loader.using( 'ext.growthExperiments.Homepage.mobile' ).done( function () {
		var homepageModules = mw.config.get( 'homepagemodules' );
		homepageModules[ 'suggested-edits' ][ 'task-preview' ] = $.extend(
			{},
			this.tasksStore.getCurrentTask(),
			{
				taskPosition: this.tasksStore.getQueuePosition() + 1
			}
		);
		mw.config.set( 'homepagemodules', homepageModules );
		require( 'ext.growthExperiments.Homepage.mobile' )
			.loadExtraDataForSuggestedEdits(
				'.growthexperiments-homepage-module-suggested-edits',
				false
			);
	}.bind( this ) );
};

/**
 * User has clicked "Done" in the dialog after selecting filters.
 *
 * @param {jQuery.Deferred} [filtersDialogProcess] Promise from the filters ProcessDialog to
 * resolve when the tasks are fetched or reject if the request failed. This is used to show the
 * loading state in the filters dialog.
 *
 * FIXME: At this point, we may already have the updated task queue since an API request is made to
 * show the task count when the filter dialog is open. When that's the case, we should use the task
 * queue from the store to avoid making two API calls for the same filters.
 */
SuggestedEditsModule.prototype.filterSelection = function ( filtersDialogProcess ) {
	this.isFirstRender = true;
	this.onFilterClose();
	var apiPromise = this.tasksStore.fetchTasks( 'suggestedEditsModule.filterSelection' );
	apiPromise.then( function () {
		if ( filtersDialogProcess ) {
			filtersDialogProcess.resolve();
		}
	} );

	if ( filtersDialogProcess ) {
		apiPromise.fail( function () {
			filtersDialogProcess.reject();
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
	this.updateFilterSelection();
	return this.tasksStore.fetchTasks( 'suggestedEditsModule.fetchTasksAndUpdateView' ).then( function () {
		this.logger.log( 'suggested-edits', this.mode, 'se-fetch-tasks' );
		return $.Deferred().resolve().promise();
	}.bind( this ) ).catch( function ( message ) {
		if ( message === null ) {
			// XHR abort, not a real error
			return;
		}
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'error', errorMessage: message } );
		return this.showCard( new ErrorCardWidget() );
	}.bind( this ) );
};

/**
 * Set the task types and topics query properties based on dialog state.
 */
SuggestedEditsModule.prototype.updateFilterSelection = function () {
	this.filtersStore.updateStatesFromTopicsFilters( this.filters.topicFiltersDialog.getEnabledFilters() );
	this.filtersStore.setSelectedTaskTypes( this.filters.taskTypeFiltersDialog.getEnabledFilters() );
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
	var shouldShowNavigation = this.currentCard instanceof EditCardWidget || !this.tasksStore.isTaskQueueEmpty(),
		// next is enabled when the end of the queue is reached to show EndOfQueueWidget
		shouldEnableNext = this.tasksStore.hasNextTask() || this.tasksStore.isEndOfTaskQueue();
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
	var action = isSwipe ? 'se-task-navigation-swipe' : 'se-task-navigation';
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
	var action = isSwipe ? 'se-task-navigation-swipe' : 'se-task-navigation';
	if ( this.tasksStore.getQueuePosition() === 0 ) {
		return;
	}
	this.isGoingBack = true;
	this.logger.log( 'suggested-edits', this.mode, action, { dir: 'prev' } );
	this.tasksStore.showPreviousTask();
};

/**
 * Called from onNextCard / filterSelection.
 *
 * Used to update the mobile summary state, show the current card based
 * on the queue position.
 */
SuggestedEditsModule.prototype.updateCurrentCard = function () {
	if ( OO.ui.isMobile() ) {
		this.updateMobileSummary();
	}
	this.showCard();
};

/**
 * Update data from PCS and AQS services for the current card
 */
SuggestedEditsModule.prototype.updateExtraDataForCurrentCard = function () {
	this.currentCard = new EditCardWidget(
		$.extend(
			{ extraDataLoaded: true, qualityGateConfig: this.tasksStore.getQualityGateConfig() },
			this.tasksStore.getCurrentTask()
		)
	);
	this.updateCardElement();
};

/**
 * Update TaskExplanationWidget for the current task
 */
SuggestedEditsModule.prototype.updateTaskExplanationWidget = function () {
	var explanationSelector = '.suggested-edits-task-explanation',
		$explanationElement = $( explanationSelector ),
		currentTask = this.tasksStore.getCurrentTask();
	if ( currentTask ) {
		$explanationElement.html(
			new TaskExplanationWidget( {
				taskType: currentTask.tasktype,
				mode: this.mode
			}, this.logger, ALL_TASK_TYPES ).$element
		);
		$explanationElement.toggle( true );
	} else {
		$explanationElement.toggle( false );
	}
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
	if ( OO.ui.isMobile() ) {
		this.updateMobileSummary();
	}
};

/**
 * Log task data for a card impression or click event to the NewcomerTask EventLogging schema.
 *
 * @param {number} cardPosition Card position in the task queue. Assumes summary data has
 *   already been loaded for this position.
 */
SuggestedEditsModule.prototype.logCardData = function ( cardPosition ) {
	this.newcomerTaskLogger.log( this.tasksStore.getCurrentTask(), cardPosition );
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
	var queuePosition = this.tasksStore.getQueuePosition();
	this.currentCard = null;

	// TODO should we log something on non-card impressions?
	if ( card ) {
		this.currentCard = card;
	} else if ( this.tasksStore.isTaskQueueEmpty() ) {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'empty' } );
		this.currentCard = new NoResultsWidget( { topicMatching: this.filtersStore.topicsEnabled } );
	} else if ( !this.tasksStore.getCurrentTask() ) {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'end' } );
		this.currentCard = new EndOfQueueWidget( { topicMatching: this.filtersStore.topicsEnabled } );
	} else {
		this.currentCard = new EditCardWidget(
			$.extend( { qualityGateConfig: this.tasksStore.getQualityGateConfig() }, this.tasksStore.getCurrentTask() )
		);
	}
	if ( this.currentCard instanceof EditCardWidget ) {
		this.logCardData( queuePosition );
		this.logger.log(
			'suggested-edits',
			this.mode,
			'se-task-impression',
			{ newcomerTaskToken: this.tasksStore.getNewcomerTaskToken() }
		);
	}
	this.updateCardElement( OO.ui.isMobile() ).then( this.updateControls.bind( this ) );

	if ( !( this.currentCard instanceof EditCardWidget ) ) {
		this.setEditWidgetDisabled( true );
		return $.Deferred().resolve();
	}
	this.setEditWidgetDisabled( false );

	return this.tasksStore.fetchExtraDataForCurrentTask().then(
		this.updateExtraDataForCurrentCard.bind( this )
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
	var isGoingBack = this.isGoingBack,
		$fakeCard = $cardElement.clone(),
		$overlayContent = this.config.$container.find( '.overlay-content' ),
		promise = $.Deferred(),
		onTransitionEnd;

	// Prevent scrolling while animation is in progress
	$overlayContent.addClass( 'is-swiping' );

	// A copy of the current card will be animated out.
	$fakeCard.addClass( [
		'suggested-edits-card-fake'
	] ).removeClass( 'suggested-edits-card' );
	$cardWrapper.append( $fakeCard );
	// The current card is positioned off screen so it can be animated in.
	$cardElement.addClass( [ 'no-transition', isGoingBack ? 'to-start' : 'to-end' ] );

	onTransitionEnd = function () {
		$fakeCard.remove();
		$cardElement.off( 'transitionend transitioncancel', onTransitionEnd );
		promise.resolve();
	};

	// A delay is added to make sure the fake card is shown and the real card is off screen.
	setTimeout( function () {
		$fakeCard.addClass( isGoingBack ? 'to-end' : 'to-start' );
		$cardElement.html( this.currentCard.$element );
		$cardElement.on( 'transitionend transitioncancel', onTransitionEnd );
		$cardElement.removeClass( [ 'no-transition', 'to-start', 'to-end' ] );
		$overlayContent.removeClass( 'is-swiping' );
	}.bind( this ), 100 );

	return promise;
};

/**
 * Update the HTML for the card with whatever is in this.currentCard.
 *
 * @param {boolean} [shouldAnimateEditCard] Whether EditCardWidget should be animated
 * @return {jQuery.Promise} Promise that resolves when card element has been updated
 */
SuggestedEditsModule.prototype.updateCardElement = function ( shouldAnimateEditCard ) {
	var cardSelector = '.suggested-edits-card',
		$cardElement = $( cardSelector ),
		$cardWrapper = $cardElement.closest( '.suggested-edits-card-wrapper' ),
		isShowingEditCardWidget = this.currentCard instanceof EditCardWidget,
		promise = $.Deferred(),
		canAnimate = shouldAnimateEditCard &&
			isShowingEditCardWidget &&
			!this.isFirstRender &&
			!this.isShowingPseudoCard;

	if ( canAnimate ) {
		this.animateCard( $cardElement, $cardWrapper ).then( function () {
			promise.resolve();
		} );
	} else {
		this.isFirstRender = false;
		$cardElement.html( this.currentCard.$element );
		promise.resolve();
	}

	this.isShowingPseudoCard = !isShowingEditCardWidget;
	$cardWrapper
		.toggleClass( 'pseudo-card', !isShowingEditCardWidget )
		.toggleClass( 'pseudo-card-eoq', this.currentCard instanceof EndOfQueueWidget );
	this.setupClickLogging();
	if ( isShowingEditCardWidget ) {
		this.setupEditTypeTracking();

	}
	return promise;
};

/**
 * Allow the user to swipe left and right to navigate through the task feed
 */
SuggestedEditsModule.prototype.setupSwipeNavigation = function () {
	var router = require( 'mediawiki.router' ),
		updateBodyClass = function ( isSwipeNavigationEnabled ) {
			$( document.body ).toggleClass(
				'growthexperiments--suggestededits-swipe-navigation-enabled',
				isSwipeNavigationEnabled
			);
		};
	this.swipeCard = new SwipePane( this.config.$element, {
		isRtl: document.documentElement.dir === 'rtl',
		isHorizontal: true
	} );
	this.swipeCard.setToStartHandler( function () {
		this.onNextCard( true );
	}.bind( this ) );
	this.swipeCard.setToEndHandler( function () {
		this.onPreviousCard( true );
	}.bind( this ) );

	// Disable scrolling on the body when the overlay is shown
	updateBodyClass( true );
	router.on( 'route', function ( e ) {
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
	var task = this.tasksStore.getCurrentTask();
	this.logCardData( this.tasksStore.getQueuePosition() );
	this.logger.log( 'suggested-edits', this.mode, action, { newcomerTaskToken: task.token } );
};

/**
 * Log click events on the task card (ie. the user visiting the task page) and pass
 * tracking data so events on the task page can be connected.
 * this.currentCard is expected to contain a valid EditCardWidget.
 */
SuggestedEditsModule.prototype.setupClickLogging = function () {
	var $link = this.currentCard.$element.find( '.se-card-content' ),
		clickId = mw.config.get( 'wgGEHomepagePageviewToken' ),
		newUrl = $link.attr( 'href' ) ?
			new mw.Uri( $link.attr( 'href' ) ).extend( {
				geclickid: clickId,
				genewcomertasktoken: this.tasksStore.getNewcomerTaskToken(),
				gesuggestededit: 1
			} ).toString() :
			'';
	$link
		.attr( 'href', newUrl )
		.on( 'click', function () {
			if ( newUrl ) {
				// Only log if this is a task card, not the skeleton loading card
				this.logEditTaskClick( 'se-task-click' );
			}
		}.bind( this ) );
};

/**
 * Rewrite the link to contain the task type ID, for later user in guidance.
 */
SuggestedEditsModule.prototype.setupEditTypeTracking = function () {
	var $link = this.currentCard.$element.find( '.se-card-content' ),
		newUrl = $link.attr( 'href' ) ?
			new mw.Uri( $link.attr( 'href' ) )
				.extend( { getasktype: this.currentCard.getTaskType() } ).toString() :
			'';
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
	$element.on( 'click', function () {
		if ( this.currentCard instanceof EditCardWidget ) {
			var qualityGate = new QualityGate( {
				gates: this.currentCard.data.qualityGateIds || [],
				gateConfig: this.tasksStore.getQualityGateConfig(),
				/* eslint-disable camelcase */
				loggers: {
					'image-recommendation': new ImageSuggestionInteractionLogger( {
						is_mobile: OO.ui.isMobile(),
						active_interface: 'qualitygate_dialog'
					} ),
					'link-recommendation': new LinkSuggestionInteractionLogger( {
						is_mobile: OO.ui.isMobile(),
						active_interface: 'qualitygate_dialog'
					} )
				},
				loggerMetadataOverrides: {
					newcomer_task_token: this.currentCard.data.token,
					homepage_pageview_token: mw.config.get(
						'wgGEHomepagePageviewToken'
					),
					page_id: this.currentCard.getPageId(),
					page_title: this.currentCard.getDbKey()
				}
				/* eslint-enable camelcase */
			} );
			return qualityGate.checkAll( this.currentCard.data.tasktype );
		}
	}.bind( this ) );
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
		classes: [ 'suggested-edits-footer-navigation-edit-button' ]
	} );
	var $editButton = $container.find( '.suggested-edits-footer-navigation-edit-button' );
	$editButton.html( this.editWidget.$element );

	// OO.ui.mixin.ButtonElement.onClick prevents the default action when the 'click'
	// event handler is set via OOJS, use the jQuery event handling mechanism instead.
	this.editWidget.$button.on( 'click', function () {
		// The widget state needs to be checked since this click event is fired regardless.
		if ( this.editWidget.isDisabled() ) {
			return;
		}
		this.logEditTaskClick( 'se-edit-button-click' );
	}.bind( this ) );
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
