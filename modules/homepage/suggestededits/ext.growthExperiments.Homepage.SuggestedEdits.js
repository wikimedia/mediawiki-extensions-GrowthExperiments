/**
 * @external HomepageModuleLogger
 * @external GrowthTasksApi
 * @external SuggestedEditCardWidget
 * @external ErrorCardWidget
 * @external NoResultsWidget
 * @external EndOfQueueWidget
 */
( function () {
	var EditCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEditCardWidget.js' ),
		EndOfQueueWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.EndOfQueueWidget.js' ),
		ErrorCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js' ),
		NoResultsWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.NoResultsWidget.js' ),
		TaskExplanationWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.TaskExplanationWidget.js' ),
		PagerWidget = require( './ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js' ),
		PreviousNextWidget = require( './ext.growthExperiments.Homepage.SuggestedEditsPreviousNextWidget.js' ),
		FiltersButtonGroupWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.FiltersWidget.js' ),
		GrowthTasksApi = require( './ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		NewcomerTaskLogger = require( './ext.growthExperiments.NewcomerTaskLogger.js' ),
		aqsConfig = require( './AQSConfig.json' ),
		taskTypes = require( './TaskTypes.json' );

	/**
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} config Configuration options
	 * @param {jQuery} config.$element SuggestedEdits widget container
	 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
	 * @param {Array<string>} config.topicPresets Lists of IDs of enabled topic filters.
	 * @param {boolean} config.topicMatching If topic matching feature is enabled in the UI
	 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
	 * @param {HomepageModuleLogger} logger
	 * @param {GrowthTasksApi} api
	 */
	function SuggestedEditsModule( config, logger, api ) {
		var $pager, $previous, $next, $filters, $filtersContainer;
		SuggestedEditsModule.super.call( this, config );

		this.config = config;
		this.logger = logger;
		this.mode = config.mode;
		this.currentCard = null;
		this.apiPromise = null;
		this.taskTypesQuery = [];
		this.topicsQuery = [];
		this.api = api;
		// Allow restoring on cancel.
		this.backup = {};
		this.backup.taskQueue = [];
		this.backup.queuePosition = 0;

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
			} )
			.toggle( false );

		// Topic presets will be null or an empty string if the user never set them.
		// It's possible that config.topicPresets is an empty array if the user set,
		// saved, then unset the topics.
		if ( !config.topicPresets ) {
			this.filters.$element.find( '.topic-filter-button' )
				.append( $( '<div>' ).addClass( 'mw-pulsating-dot' ) );
		}

		this.pager = new PagerWidget().toggle( false );
		this.previousWidget = new PreviousNextWidget( { direction: 'Previous' } )
			.connect( this, { click: 'onPreviousCard' } )
			.toggle( false );
		this.nextWidget = new PreviousNextWidget( { direction: 'Next' } )
			.connect( this, { click: 'onNextCard' } )
			.toggle( false );

		$pager = this.$element.find( '.suggested-edits-pager' );
		if ( !$pager.length ) {
			$pager = $( '<div>' ).addClass( 'suggested-edits-pager' ).appendTo( this.$element );
		}
		$previous = this.$element.find( '.suggested-edits-previous' );
		if ( !$previous.length ) {
			$previous = $( '<div>' ).addClass( 'suggested-edits-previous' ).appendTo( this.$element );
		}
		$next = this.$element.find( '.suggested-edits-next' );
		if ( !$next.length ) {
			$next = $( '<div>' ).addClass( 'suggested-edits-next' ).appendTo( this.$element );
		}

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

		$pager.append( this.pager.$element );
		$previous.append( this.previousWidget.$element );
		$next.append( this.nextWidget.$element );
		$filters.append( this.filters.$element );
	}

	OO.inheritClass( SuggestedEditsModule, OO.ui.Widget );

	SuggestedEditsModule.prototype.backupState = function () {
		this.backup.taskQueue = this.taskQueue;
		this.backup.queuePosition = this.queuePosition;
	};

	SuggestedEditsModule.prototype.restoreState = function () {
		this.taskQueue = this.backup.taskQueue;
		this.queuePosition = this.backup.queuePosition;
		this.filters.updateMatchCount( this.taskQueue.length );
		this.showCard();
	};

	/**
	 * User has clicked "Done" in the dialog after selecting filters.
	 */
	SuggestedEditsModule.prototype.filterSelection = function () {
		if ( !this.apiPromise ) {
			this.apiPromise = this.api.fetchTasks( this.taskTypesQuery, this.topicsQuery, {
				isMobile: OO.ui.isMobile(),
				context: 'suggestedEditsModule.filterSelection'
			} );
		}
		this.apiPromise.then( function () {
			this.showCard();
			this.preloadNextCard();
		}.bind( this ) );
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
	 * @return {jQuery.Promise} Status promise. It never fails; errors are handled internally
	 *   by rendering an error card.
	 */
	SuggestedEditsModule.prototype.fetchTasksAndUpdateView = function () {
		this.currentCard = null;
		this.taskQueue = [];
		this.queuePosition = 0;

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
			this.filters.updateMatchCount( this.taskQueue.length );
			return $.Deferred().resolve().promise();
		}
		this.apiPromise = this.api.fetchTasks( this.taskTypesQuery, this.topicsQuery, {
			isMobile: OO.ui.isMobile(),
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

			this.taskQueue = data.tasks;
			this.filters.updateMatchCount( this.taskQueue.length );
			// FIXME these are the current values of the filters, not the ones we are just about
			//   to display. Unlikely to cause much discrepancy though.
			extraData.taskTypes = this.taskTypesQuery;
			if ( this.config.topicMatching ) {
				extraData.topics = this.topicsQuery;
			}
			// FIXME should this be capped to 200 or show the total server-side result count?
			extraData.taskCount = this.taskQueue.length;
			this.logger.log( 'suggested-edits', this.mode, 'se-fetch-tasks' );
			// TODO: Eventually this will become the skeleton card widget
			this.currentCard = new NoResultsWidget( { topicMatching: this.config.topicMatching } );
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
		if ( this.taskQueue.length ) {
			this.pager.setMessage( this.queuePosition + 1, this.taskQueue.length );
			this.pager.toggle( true );
		} else {
			this.pager.toggle( false );
		}
	};

	SuggestedEditsModule.prototype.updatePreviousNextButtons = function () {
		var hasPrevious = this.queuePosition > 0,
			hasNext = this.queuePosition < this.taskQueue.length;
		this.previousWidget.setDisabled( !hasPrevious );
		this.nextWidget.setDisabled( !hasNext );
		this.previousWidget.toggle( this.taskQueue.length );
		this.nextWidget.toggle( this.taskQueue.length );
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

	SuggestedEditsModule.prototype.onNextCard = function () {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-navigation', { dir: 'next' } );
		this.queuePosition = this.queuePosition + 1;
		this.showCard();
		this.preloadNextCard();
	};

	SuggestedEditsModule.prototype.onPreviousCard = function () {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-navigation', { dir: 'prev' } );
		this.queuePosition = this.queuePosition - 1;
		this.showCard();
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
		var newcomerTaskLogger = new NewcomerTaskLogger(),
			task = this.taskQueue[ cardPosition ];

		return newcomerTaskLogger.log( task, cardPosition );
	};

	/**
	 * Display the given card, or the current card.
	 * Sets this.currentCard.
	 *
	 * @param {SuggestedEditCardWidget|ErrorCardWidget|NoResultsWidget|EndOfQueueWidget|null} card
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
		}
		if ( this.currentCard ) {
			this.updateCardElement();
			this.updateControls();
			return $.Deferred().resolve();
		}

		this.currentCard = new EditCardWidget( this.taskQueue[ queuePosition ] );

		this.updateCardElement();
		this.updateControls();
		return this.getExtraDataAndUpdateQueue( queuePosition ).then( function () {
			if ( queuePosition !== this.queuePosition ) {
				return;
			}
			this.currentCard = new EditCardWidget(
				$.extend( { extraDataLoaded: true }, this.taskQueue[ queuePosition ] )
			);
			this.updateCardElement();
			this.logger.log(
				'suggested-edits',
				this.mode,
				'se-task-impression',
				{ newcomerTaskToken: this.logCardData( queuePosition ) }
			);
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
				isMobile: OO.ui.isMobile(),
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
	 * Update the HTML for the card with whatever is in this.currentCard.
	 */
	SuggestedEditsModule.prototype.updateCardElement = function () {
		var cardSelector = '.suggested-edits-card',
			$cardElement = $( cardSelector );
		$cardElement.html( this.currentCard.$element );
		$cardElement.closest( '.suggested-edits-card-wrapper' )
			.toggleClass( 'pseudo-card', !( this.currentCard instanceof EditCardWidget ) )
			.toggleClass( 'pseudo-card-eoq', this.currentCard instanceof EndOfQueueWidget );
		this.setupClickLogging();
		if ( this.currentCard instanceof EditCardWidget ) {
			this.setupEditTypeTracking();
		}
	};

	/**
	 * Update the control chrome around the card depending on the state of the queue and query.
	 */
	SuggestedEditsModule.prototype.updateControls = function () {
		this.setFilterQueriesFromDialogState();
		this.filters.toggle( true );
		this.filters.updateButtonLabelAndIcon( this.taskTypesQuery, this.topicsQuery );
		this.updatePager();
		this.updatePreviousNextButtons();
		this.updateTaskExplanationWidget();
	};

	/**
	 * Log click events on the task card (ie. the user visiting the task page) and pass
	 * tracking data so events on the task page can be connected.
	 * this.currentCard is expected to contain a valid EditCardWidget.
	 */
	SuggestedEditsModule.prototype.setupClickLogging = function () {
		var $link = this.currentCard.$element.find( '.se-card-content' ),
			clickId = mw.config.get( 'wgGEHomepagePageviewToken' ),
			newUrl = new mw.Uri( $link.attr( 'href' ) ).extend( { geclickid: clickId } ).toString();

		$link
			.attr( 'href', newUrl )
			.on( 'click', function () {
				this.logger.log( 'suggested-edits', this.mode, 'se-task-click',
					{ newcomerTaskToken: this.logCardData( this.queuePosition ) } );
			}.bind( this ) );
	};

	/**
	 * Rewrite the link to contain the task type ID, for later user in guidance.
	 */
	SuggestedEditsModule.prototype.setupEditTypeTracking = function () {
		var $link = this.currentCard.$element.find( '.se-card-content' ),
			newUrl = new mw.Uri( $link.attr( 'href' ) )
				.extend( { getasktype: this.currentCard.getTaskType() } ).toString();
		$link.attr( 'href', newUrl );
	};

	/**
	 * Set up the suggested edits module within the given container, fetch the tasks
	 * and display the first.
	 *
	 * @param {jQuery} $container
	 * @return {jQuery.Promise} Status promise.
	 */
	function initSuggestedTasks( $container ) {
		var initTime = mw.now(),
			suggestedEditsModule,
			api = new GrowthTasksApi( {
				taskTypes: taskTypes,
				suggestedEditsConfig: require( './config.json' ),
				aqsConfig: aqsConfig
			} ),
			preferences = api.getPreferences(),
			$wrapper = $container.find( '.suggested-edits-module-wrapper' ),
			mode = $wrapper.closest( '.growthexperiments-homepage-module' ).data( 'mode' );

		if ( !$wrapper.length ) {
			return;
		}

		suggestedEditsModule = new SuggestedEditsModule(
			{
				$element: $wrapper,
				taskTypePresets: preferences.taskTypes,
				topicPresets: preferences.topics,
				topicMatching: mw.config.get( 'GEHomepageSuggestedEditsEnableTopics' ),
				mode: mode
			},
			new Logger(
				mw.config.get( 'wgGEHomepageLoggingEnabled' ),
				mw.config.get( 'wgGEHomepagePageviewToken' )
			),
			api );
		return suggestedEditsModule.fetchTasksAndUpdateView()
			.then( function () {
				suggestedEditsModule.filters.toggle( true );
				if ( suggestedEditsModule.currentCard instanceof ErrorCardWidget ) {
					// currentCard was set by fetchTasksAndUpdateView, do not overwrite it
					return $.Deferred().resolve();
				}
				return suggestedEditsModule.showCard();
			} ).done( function () {
				mw.track(
					'timing.growthExperiments.specialHomepage.modules.suggestedEditsLoadingComplete.' +
						( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
					mw.now() - initTime
				);
				// Use done instead of then because 1) we don't want to make the caller
				// wait for the preload; 2) failed preloads should not result in an
				// error card, as they don't affect the current card. The load will be
				// retried when the user navigates.
				suggestedEditsModule.preloadNextCard();
			} );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode.
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js.
	// Export setup state so the caller can wait for it when setting up the module
	// on the client side.
	// eslint-disable-next-line no-jquery/no-global-selector
	module.exports = initSuggestedTasks( $( '.growthexperiments-homepage-container' ) );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'suggested-edits' ) {
			initSuggestedTasks( $content );
		}
	} );
}() );
