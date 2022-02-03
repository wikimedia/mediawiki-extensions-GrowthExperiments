var EditCardWidget = require( './EditCardWidget.js' ),
	EndOfQueueWidget = require( './EndOfQueueWidget.js' ),
	ErrorCardWidget = require( './ErrorCardWidget.js' ),
	NoResultsWidget = require( './NoResultsWidget.js' ),
	TaskExplanationWidget = require( './TaskExplanationWidget.js' ),
	PagerWidget = require( './PagerWidget.js' ),
	PreviousNextWidget = require( './PreviousNextWidget.js' ),
	FiltersButtonGroupWidget = require( './FiltersWidget.js' ),
	NewcomerTaskLogger = require( './NewcomerTaskLogger.js' ),
	SwipePane = require( '../ui-components/SwipePane.js' ),
	QualityGate = require( './QualityGate.js' ),
	ImageSuggestionInteractionLogger = require( '../ext.growthExperiments.StructuredTask/addimage/ImageSuggestionInteractionLogger.js' ),
	LinkSuggestionInteractionLogger = require( '../ext.growthExperiments.StructuredTask/addlink/LinkSuggestionInteractionLogger.js' );

/**
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {Object} config Configuration options
 * @param {jQuery} config.$container Module container
 * @param {jQuery} config.$element SuggestedEdits widget container
 * @param {jQuery} config.$nav Navigation element (if navigation is separate from $element)
 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
 * @param {Array<string>|null} config.topicPresets Lists of IDs of enabled topic filters.
 * @param {boolean} config.topicMatching If topic matching feature is enabled in the UI
 * @param {string} config.mode Rendering mode. See constants in IDashboardModule.php
 * @param {Object} config.qualityGateConfig Quality gate configuration exported from TaskSet.php
 * @param {HomepageModuleLogger} logger
 * @param {mw.libs.ge.GrowthTasksApi} api
 */
function SuggestedEditsModule( config, logger, api ) {
	var $previous, $next, $filters, $filtersContainer, $navContainer;
	SuggestedEditsModule.super.call( this, config );
	this.config = config;
	this.logger = logger;
	this.mode = config.mode;
	this.currentCard = null;
	this.apiPromise = null;
	this.newcomerTaskToken = null;
	this.apiFetchMoreTasksPromise = null;
	this.taskTypesQuery = [];
	this.topicsQuery = [];
	this.api = api;
	/** @property {mw.libs.ge.TaskData[]} taskQueue Fetched task data */
	this.taskQueue = [];
	this.taskQueueLoading = false;
	/**
	 * @property {number} taskCount Total number of tasks that match the selected filters.
	 * This can be greater than this.taskQueue.length since the task data is lazy loaded. *
	 */
	this.taskCount = 0;
	this.editWidget = null;
	this.isFirstRender = true;
	this.isShowingPseudoCard = true;
	this.qualityGateConfig = config.qualityGateConfig;
	// Allow restoring on cancel.
	this.backup = {};
	this.backup.taskQueue = [];
	this.backup.queuePosition = 0;
	this.backup.taskCount = 0;

	this.filters = new FiltersButtonGroupWidget( {
		taskTypePresets: config.taskTypePresets,
		topicPresets: config.topicPresets,
		topicMatching: config.topicMatching,
		mode: this.mode
	}, logger )
		.connect( this, {
			search: 'fetchTasksAndUpdateView',
			done: 'filterSelection',
			cancel: 'restoreState',
			open: 'backupState'
		} );
	this.newcomerTaskLogger = new NewcomerTaskLogger();

	// Topic presets will be null if the user never set them.
	// It's possible that config.topicPresets is an empty array if the user set,
	// saved, then unset the topics.
	if ( !config.topicPresets ) {
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
}

OO.inheritClass( SuggestedEditsModule, OO.ui.Widget );

SuggestedEditsModule.prototype.backupState = function () {
	this.backup.taskQueue = this.taskQueue;
	this.backup.queuePosition = this.queuePosition;
	this.backup.taskCount = this.taskCount;
};

SuggestedEditsModule.prototype.restoreState = function () {
	this.isFirstRender = true;
	this.taskQueue = this.backup.taskQueue;
	this.queuePosition = this.backup.queuePosition;
	this.taskCount = this.backup.taskCount;
	this.taskQueueLoading = false;
	this.filters.updateMatchCount( this.taskCount );
	if ( this.taskQueue.length && OO.ui.isMobile() ) {
		this.updateMobileSummarySmallTaskCard();
	}
	this.showCard();
};

/**
 * Update the mobile summary small task card HTML on Special:Homepage
 * with the first card in the task queue.
 */
SuggestedEditsModule.prototype.updateMobileSummarySmallTaskCard = function () {
	mw.loader.using( 'ext.growthExperiments.Homepage.mobile' ).done( function () {
		var homepageModules = mw.config.get( 'homepagemodules' );
		homepageModules[ 'suggested-edits' ][ 'task-preview' ] = this.taskQueue[ this.queuePosition ];
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
 */
SuggestedEditsModule.prototype.filterSelection = function ( filtersDialogProcess ) {
	this.isFirstRender = true;
	if ( !this.apiPromise ) {
		this.apiPromise = this.api.fetchTasks( this.taskTypesQuery, this.topicsQuery, {
			context: 'suggestedEditsModule.filterSelection'
		} );
	}
	this.apiPromise.then( function () {
		this.updateCardAndPreloadNext();
		if ( filtersDialogProcess ) {
			filtersDialogProcess.resolve();
		}
	}.bind( this ) );

	if ( filtersDialogProcess ) {
		this.apiPromise.fail( function () {
			filtersDialogProcess.reject();
		} );
	}
};

/**
 * Set the task types and topics query properties based on dialog state.
 */
SuggestedEditsModule.prototype.setFilterQueriesFromDialogState = function () {
	this.taskTypesQuery = this.filters.taskTypeFiltersDialog.getEnabledFilters();
	this.topicsQuery = this.config.topicMatching &&
	this.filters.topicFiltersDialog.getEnabledFilters().length ?
		this.filters.topicFiltersDialog.getEnabledFilters() :
		[];
};

/**
 * Fetch suggested edits from ApiQueryGrowthTasks and update the view and internal state.
 *
 * @param {Object} [options]
 * @param {mw.libs.ge.TaskData} [options.firstTask] Override for the first task, which might
 *   have been preloaded earlier. If the task is already in the real result set, it will be
 *   hoisted to the front; if not, it will be prepended.
 * @return {jQuery.Promise} Status promise. It never fails; errors are handled internally
 *   by rendering an error card.
 */
SuggestedEditsModule.prototype.fetchTasksAndUpdateView = function ( options ) {
	options = options || {};
	this.currentCard = null;
	this.taskQueue = [];
	this.queuePosition = 0;
	this.taskQueueLoading = true;

	if ( options.firstTask ) {
		// The first card is already available (preloaded on the server side), display it
		// to reduce wait time.
		this.taskQueue = [ options.firstTask ];
		this.showCard();
	}

	if ( !this.filters.taskTypeFiltersDialog ) {
		// Module hasn't finished initializing, return.
		return $.Deferred().resolve().promise();
	}

	if ( this.apiPromise ) {
		this.apiPromise.abort();
	}

	this.setFilterQueriesFromDialogState();

	if ( !this.taskTypesQuery.length ) {
		// User has deselected all checkboxes; update the count.
		this.filters.updateMatchCount( this.taskCount );
		return $.Deferred().resolve().promise();
	}
	this.apiPromise = this.api.fetchTasks( this.taskTypesQuery, this.topicsQuery, {
		context: 'suggestedEditsModule.fetchTasksAndUpdateView'
	} );
	return this.apiPromise.then( function ( data ) {
		// HomepageModuleLogger adds this to the log data automatically
		var extraData = mw.config.get( 'wgGEHomepageModuleActionData-suggested-edits' );
		if ( !extraData ) {
			// when initializing the module on the client side, this is not set
			extraData = {};
			mw.config.set( 'wgGEHomepageModuleActionData-suggested-edits', extraData );
		}

		this.taskQueueLoading = false;
		this.taskQueue = data.tasks;
		if ( options.firstTask ) {
			this.taskQueue = this.taskQueue.filter( function ( task ) {
				return task.title !== options.firstTask.title;
			} );
			this.taskQueue.unshift( options.firstTask );
		}
		this.taskCount = data.count;
		this.filters.updateMatchCount( this.taskCount );
		if ( data.tasks && data.tasks.length ) {
			this.maybeUpdateQualityGateConfig( data.tasks[ 0 ] );
		}
		// FIXME these are the current values of the filters, not the ones we are just about
		//   to display. Unlikely to cause much discrepancy though.
		extraData.taskTypes = this.taskTypesQuery;
		if ( this.config.topicMatching ) {
			extraData.topics = this.topicsQuery;
		}

		extraData.taskCount = data.count;
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

SuggestedEditsModule.prototype.updatePager = function () {
	var seModuleActionData = mw.config.get( 'wgGEHomepageModuleActionData-suggested-edits' ) || {},
		homepageModulesConfig = mw.config.get( 'homepagemodules' );
	if ( this.taskQueue.length ) {
		var totalCount = this.taskCount ||
			seModuleActionData.taskCount ||
			homepageModulesConfig[ 'suggested-edits' ][ 'task-count' ];
		this.pager.setMessage( this.queuePosition + 1, totalCount );
		this.pager.toggle( true );
	} else {
		this.pager.toggle( this.currentCard instanceof EditCardWidget );
	}
};

SuggestedEditsModule.prototype.updatePreviousNextButtons = function () {
	var hasPrevious = !this.taskQueueLoading && this.queuePosition > 0,
		hasNext = !this.taskQueueLoading && this.queuePosition < this.taskQueue.length;
	this.previousWidget.setDisabled( !hasPrevious );
	this.nextWidget.setDisabled( !hasNext );
	this.previousWidget.toggle( this.currentCard instanceof EditCardWidget ||
		this.taskQueue.length );
	this.nextWidget.toggle( this.currentCard instanceof EditCardWidget ||
		this.taskQueue.length );
};

/**
 * Preload extra (non-action-API) data for the next card. Does not change the view.
 *
 * @return {jQuery.Promise} Loading status.
 */
SuggestedEditsModule.prototype.preloadNextCard = function () {
	if ( this.taskQueue[ this.queuePosition + 1 ] &&
		!this.taskQueue[ this.queuePosition + 1 ].extract
	) {
		return this.getExtraDataAndUpdateQueue( this.queuePosition + 1 );
	}
};

/**
 * Show the next card if it's available
 *
 * @param {boolean} [isSwipe] Whether the action is triggered via swipe action
 */
SuggestedEditsModule.prototype.onNextCard = function ( isSwipe ) {
	var action = isSwipe ? 'se-task-navigation-swipe' : 'se-task-navigation';
	if ( this.queuePosition === this.taskQueue.length ) {
		// EndOfQueueWidget is already shown.
		return;
	}
	this.isGoingBack = false;
	this.logger.log( 'suggested-edits', this.mode, action, { dir: 'next' } );
	var fetchMoreTasksPromise = $.Deferred().resolve();
	this.queuePosition = this.queuePosition + 1;
	// Reload the queue.
	if ( this.queuePosition === this.taskQueue.length - 1 ) {
		fetchMoreTasksPromise = this.fetchMoreTasks();
		// Ellipsis is the stand-in for a loading indicator.
		this.nextWidget.setIcon( 'ellipsis' );
		this.nextWidget.setDisabled( true );
	}
	fetchMoreTasksPromise.done( function () {
		this.updateCardAndPreloadNext();
	}.bind( this ) );
};

/**
 * Called from onNextCard / filterSelection.
 *
 * Used to update the mobile summary small task card, show the current card based
 * on the queue position, and preload the next card.
 */
SuggestedEditsModule.prototype.updateCardAndPreloadNext = function () {
	if ( this.taskQueue.length && OO.ui.isMobile() ) {
		this.updateMobileSummarySmallTaskCard();
	}
	this.showCard();
	this.preloadNextCard();
};

/**
 * Fetch tasks and append to existing task queue, deduplicating as needed.
 *
 * @return {jQuery.Promise} fetchTasks promise from the GrowthTasksApi.
 */
SuggestedEditsModule.prototype.fetchMoreTasks = function () {
	if ( this.apiFetchMoreTasksPromise ) {
		this.apiFetchMoreTasksPromise.abort();
	}
	var i, existingPageIds = [];
	for ( i = 0; i < this.taskQueue.length; i++ ) {
		existingPageIds.push( this.taskQueue[ i ].pageId );
	}
	var config = {
		context: 'suggestedEditsModule.fetchMoreTasksOnNextCard'
	};
	if ( existingPageIds.length ) {
		config.excludePageIds = existingPageIds;
	}
	this.apiFetchMoreTasksPromise = this.api.fetchTasks(
		this.taskTypesQuery,
		this.topicsQuery,
		config
	);
	this.apiFetchMoreTasksPromise.done( function ( data ) {
		this.taskQueue = this.taskQueue.concat( data.tasks || [] );
		this.nextWidget.setDisabled( !!data.tasks.length );
		this.nextWidget.setIcon( 'arrowNext' );
	}.bind( this ) );
	return this.apiFetchMoreTasksPromise;
};

/**
 * Show the previous card if it's available
 *
 * @param {boolean} [isSwipe] Whether the action is triggered via swipe action
 */
SuggestedEditsModule.prototype.onPreviousCard = function ( isSwipe ) {
	var action = isSwipe ? 'se-task-navigation-swipe' : 'se-task-navigation';
	if ( this.queuePosition === 0 ) {
		return;
	}
	this.isGoingBack = true;
	this.logger.log( 'suggested-edits', this.mode, action, { dir: 'prev' } );
	this.queuePosition = this.queuePosition - 1;
	this.showCard();
	if ( OO.ui.isMobile() ) {
		this.updateMobileSummarySmallTaskCard();
	}
};

SuggestedEditsModule.prototype.updateTaskExplanationWidget = function () {
	var explanationSelector = '.suggested-edits-task-explanation',
		$explanationElement = $( explanationSelector );
	if ( this.queuePosition < this.taskQueue.length ) {
		$explanationElement.html(
			new TaskExplanationWidget( {
				taskType: this.taskQueue[ this.queuePosition ].tasktype,
				mode: this.mode
			}, this.logger ).$element
		);
		$explanationElement.toggle( true );
	} else {
		$explanationElement.toggle( false );
	}
};

/**
 * Log task data for a card impression or click event to the NewcomerTask EventLogging schema.
 *
 * @param {number} cardPosition Card position in the task queue. Assumes summary data has
 *   already been loaded for this position.
 * @return {string} Token to reference the log entry with.
 */
SuggestedEditsModule.prototype.logCardData = function ( cardPosition ) {
	var task = this.taskQueue[ cardPosition ];
	this.newcomerTaskLogger.log( task, cardPosition );
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
	var queuePosition = this.queuePosition;
	this.currentCard = null;

	// TODO should we log something on non-card impressions?
	if ( card ) {
		this.currentCard = card;
	} else if ( !this.taskQueue.length ) {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'empty' } );
		this.currentCard = new NoResultsWidget( { topicMatching: this.config.topicMatching } );
	} else if ( !this.taskQueue[ queuePosition ] ) {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
			{ type: 'end' } );
		this.currentCard = new EndOfQueueWidget( { topicMatching: this.config.topicMatching } );
	} else {
		this.currentCard = new EditCardWidget(
			$.extend( { qualityGateConfig: this.qualityGateConfig }, this.taskQueue[ queuePosition ] )
		);
	}
	if ( this.currentCard instanceof EditCardWidget ) {
		var task = this.taskQueue[ queuePosition ];
		this.newcomerTaskToken = task.token;
		this.logCardData( queuePosition );
		this.logger.log(
			'suggested-edits',
			this.mode,
			'se-task-impression',
			{ newcomerTaskToken: task.token }
		);
	}
	this.updateCardElement( OO.ui.isMobile() ).then( this.updateControls.bind( this ) );

	if ( !( this.currentCard instanceof EditCardWidget ) ) {
		this.setEditWidgetDisabled( true );
		return $.Deferred().resolve();
	}
	this.setEditWidgetDisabled( false );

	return this.getExtraDataAndUpdateQueue( queuePosition ).then( function () {
		if ( queuePosition !== this.queuePosition ) {
			return;
		}
		this.currentCard = new EditCardWidget(
			$.extend( { extraDataLoaded: true, qualityGateConfig: this.qualityGateConfig }, this.taskQueue[ queuePosition ] )
		);
		this.updateCardElement();
	}.bind( this ) );
};

/**
 * Gets extra data which is not reliably available via the action API (we use a nondeterministic
 * generator so we cannot do query continuation, plus we reorder the results so performance
 * would be unpredictable) from the PCS and AQS services.
 *
 * @param {number} taskQueuePosition
 * @return {jQuery.Promise} Promise reflecting the status of the PCS request
 *   (AQS errors are ignored). Does not return any value; instead,
 *   SuggestedEditsModule.taskQueue will be updated.
 */
SuggestedEditsModule.prototype.getExtraDataAndUpdateQueue = function ( taskQueuePosition ) {
	var pcsPromise, aqsPromise, preloaded,
		apiConfig = {
			context: 'suggestedEditsModule.getExtraDataAndUpdateQueue'
		},
		suggestedEditData = this.taskQueue[ taskQueuePosition ];
	if ( !suggestedEditData ) {
		return $.Deferred().resolve().promise();
	}

	pcsPromise = this.api.getExtraDataFromPcs( suggestedEditData, apiConfig );
	aqsPromise = this.api.getExtraDataFromAqs( suggestedEditData, apiConfig );
	// We might have the thumbnail URL already, or we might receive it later from PCS.
	// Start preloading as soon as possible.
	preloaded = this.preloadCardImage( suggestedEditData );
	if ( !preloaded ) {
		pcsPromise.done( function () {
			this.preloadCardImage( suggestedEditData );
		}.bind( this ) );
	}
	return $.when( pcsPromise, aqsPromise ).then( function () {
		// Data is updated in-place so this is probably not necessary, but just in case
		// something replaced the object, re-replace it for good measure.
		this.taskQueue[ taskQueuePosition ] = suggestedEditData;
	}.bind( this ) ).catch( function () {
		// We don't need to do anything here since the page views and RESTBase
		// calls are for supplemental data; we just need to catch any exception
		// so that the card can render with the data we have from ApiQueryGrowthTasks.
	} );
};

/**
 * Preload the task card image.
 *
 * @param {Object} task Task data, as returned by GrowthTasksApi.
 * @return {boolean} Whether preloading has been started.
 */
SuggestedEditsModule.prototype.preloadCardImage = function ( task ) {
	if ( task.thumbnailSource ) {
		$( '<img>' ).attr( 'src', task.thumbnailSource );
		return true;
	}
	return false;
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
	this.setFilterQueriesFromDialogState();
	this.filters.updateButtonLabelAndIcon( this.taskTypesQuery, this.topicsQuery );
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
	var task = this.taskQueue[ this.queuePosition ];
	this.logCardData( this.queuePosition );
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
				genewcomertasktoken: this.newcomerTaskToken,
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
				gateConfig: this.qualityGateConfig,
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

/**
 * Update the quality config if it's included in the task data.
 *
 * The quality gate config is initially set to the value from the task preview data. When the
 * task preview data is not available, the tasks are still fetched on the client side (the no
 * suggestions found card is shown first) so the quality config should be updated accordingly.
 *
 * @param {mw.libs.ge.TaskData} taskData
 */
SuggestedEditsModule.prototype.maybeUpdateQualityGateConfig = function ( taskData ) {
	if ( taskData.qualityGateConfig ) {
		this.qualityGateConfig = taskData.qualityGateConfig;
	}
};

module.exports = SuggestedEditsModule;
