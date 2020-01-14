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
		taskTypes = require( './TaskTypes.json' ),
		aqsConfig = require( './AQSConfig.json' ),
		initialTaskTypes = [ 'copyedit', 'links' ].filter( function ( taskType ) {
			return taskType in taskTypes;
		} );

	/**
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} config Configuration options
	 * @param {jQuery} config.$element SuggestedEdits widget container
	 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
	 * @param {Array<string>} config.topicPresets Lists of IDs of enabled topic filters.
	 * @param {bool} config.topicMatching If topic matching feature is enabled in the UI
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

		this.filters = new FiltersButtonGroupWidget( {
			taskTypePresets: config.taskTypePresets,
			topicPresets: config.topicPresets,
			topicMatching: config.topicMatching,
			mode: this.mode
		}, logger )
			.connect( this, {
				search: 'fetchTasksAndUpdateView',
				done: 'filterSelection'
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

	/**
	 * User has clicked "Done" in the dialog after selecting filters.
	 */
	SuggestedEditsModule.prototype.filterSelection = function () {
		if ( !this.apiPromise ) {
			this.apiPromise = this.api.fetchTasks( this.taskTypesQuery, this.topicsQuery );
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
		this.apiPromise = this.api.fetchTasks( this.taskTypesQuery, this.topicsQuery );
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
		}.bind( this ) ).catch( function ( error, details ) {
			if ( error === 'http' && details && details.textStatus === 'abort' ) {
				// Don't show error card for XHR abort.
				return;
			}
			mw.log.error( 'Fetching task suggestions failed:', error, details );
			// TODO log more information about the error
			this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
				{ type: 'error' } );
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
	 * Extract log data for use by HomepageModuleLogger.log in card impression and click events.
	 * @param {int} cardPosition Card position in the task queue. Assumes summary data has
	 *   already been loaded for this position.
	 * @return {Object<string>}
	 */
	SuggestedEditsModule.prototype.getCardLogData = function ( cardPosition ) {
		var logData,
			suggestedEditData = this.taskQueue[ cardPosition ];
		logData = {
			taskType: suggestedEditData.tasktype,
			maintenanceTemplates: suggestedEditData.maintenanceTemplates,
			hasImage: !!suggestedEditData.thumbnailSource,
			ordinalPosition: cardPosition,
			pageviews: suggestedEditData.pageviews,
			pageTitle: suggestedEditData.title,
			pageId: suggestedEditData.pageId,
			revisionId: suggestedEditData.revisionId
			// the page token is automatically added by the logger
		};
		if ( suggestedEditData.topics && suggestedEditData.topics.length ) {
			logData.topic = suggestedEditData.topics[ 0 ][ 0 ];
			logData.matchScore = suggestedEditData.topics[ 0 ][ 1 ];
		}
		return logData;
	};

	/**
	 * Display the given card, or the current card.
	 * Sets this.currentCard.
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
				this.getCardLogData( queuePosition )
			);
		}.bind( this ) );
	};

	/**
	 * Gets data which is not reliably available via the action API (we use a nondeterministic
	 * generator so we cannot do query continuation, plus we reorder the results so performance
	 * would be unpredictable). Specifically, lead section extracts from the Page Content Service
	 * summary API and pageviews from the Analytics Query Service.
	 * The PCS endpoint can be customized via $wgGERestbaseUrl (by default will be assumed to be
	 * local; setting it to null will disable text extracts), the AQS endpoint via
	 * $wgPageViewInfoWikimediaDomain (by default will use the Wikimedia instance).
	 * @param {int} taskQueuePosition
	 * @return {jQuery.Promise} Promise reflecting the status of the PCS request
	 *   (AQS errors are ignored). Does not return any value; instead,
	 *   SuggestedEditsModule.taskQueue will be updated.
	 */
	SuggestedEditsModule.prototype.getExtraDataAndUpdateQueue = function ( taskQueuePosition ) {
		var pcsPromise, aqsPromise,
			suggestedEditData = this.taskQueue[ taskQueuePosition ];
		if ( !suggestedEditData ) {
			return $.Deferred().resolve().promise();
		}

		pcsPromise = ( 'extract' in suggestedEditData ) ?
			$.Deferred().resolve( {} ).promise() :
			this.getExtraDataFromPcs( suggestedEditData.title );
		aqsPromise = ( 'pageviews' in suggestedEditData ) ?
			$.Deferred().resolve( {} ).promise() :
			this.getExtraDataFromAqs( suggestedEditData.title );
		return $.when( pcsPromise, aqsPromise ).then( function ( pcsData, aqsData ) {
			// If the data is already loaded, xxxData will be an empty {}, so
			// we need to be careful never to override real fields with missing ones.
			if ( pcsData && pcsData.extract ) {
				suggestedEditData.extract = pcsData.extract;
			}
			// Normally we use the thumbnail source from the action API, this is only a fallback.
			// It is used for some beta wiki configurations and local setups, and also when the
			// action API data is missing due to query+pageimages having a smaller max limit than
			// query+growthtasks.
			if ( !suggestedEditData.thumbnailSource && pcsData && pcsData.thumbnailSource ) {
				suggestedEditData.thumbnailSource = pcsData.thumbnailSource;
			}
			// AQS never returns data with a pageview total of 0, it just errors out if there are no
			// views. Even if it did, it would probably be better not to show 0 to the user.
			if ( aqsData && aqsData.pageviews ) {
				suggestedEditData.pageviews = aqsData.pageviews;
			}
			// Update the suggested edit data so we don't need to fetch it again
			// if the user views the card more than once.
			this.taskQueue[ taskQueuePosition ] = suggestedEditData;
		}.bind( this ) ).catch( function () {
			// We don't need to do anything here since the page views and RESTBase
			// calls are for supplemental data; we just need to catch any exception
			// so that the card can render with the data we have from ApiQueryGrowthTasks.
		} );
	};

	/**
	 * Get extracts and page images from PCS.
	 * @param {string} title
	 * @return {jQuery.Promise<Object>}
	 * @see ::getExtraDataAndUpdateQueue
	 */
	SuggestedEditsModule.prototype.getExtraDataFromPcs = function ( title ) {
		var encodedTitle,
			apiUrlBase = mw.config.get( 'wgGERestbaseUrl' );

		if ( !apiUrlBase ) {
			// Don't fail worse then we have to when RESTBase is not installed.
			return $.Deferred.resolve( '' ).promise();
		}
		encodedTitle = encodeURIComponent( title.replace( / /g, '_' ) );
		return $.get( apiUrlBase + '/page/summary/' + encodedTitle ).then( function ( data ) {
			var pcsData = {};
			pcsData.extract = data.extract;
			if ( data.thumbnail ) {
				pcsData.thumbnailSource = data.thumbnail.source;
			}
			return pcsData;
		} );
	};

	/**
	 * Get pageview data from AQS.
	 * @param {string} title
	 * @return {jQuery.Promise<int|null>}
	 * @see ::getExtraDataAndUpdateQueue
	 */
	SuggestedEditsModule.prototype.getExtraDataFromAqs = function ( title ) {
		var encodedTitle, pageviewsApiUrl, day, firstPageviewDay, lastPageviewDay;

		encodedTitle = encodeURIComponent( title.replace( / /g, '_' ) );
		// Get YYYYMMDD timestamps of 2 days ago (typically the last day that has full
		// data in AQS) and 60+2 days ago, using Javascript's somewhat cumbersome date API
		day = new Date();
		day.setDate( day.getDate() - 2 );
		lastPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		day.setDate( day.getDate() - 60 );
		firstPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		pageviewsApiUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' +
			aqsConfig.project + '/all-access/user/' + encodedTitle + '/daily/' +
			firstPageviewDay + '/' + lastPageviewDay;

		return $.get( pageviewsApiUrl ).then( function ( data ) {
			var pageviews = 0;
			( data.items || [] ).forEach( function ( item ) {
				pageviews += item.views;
			} );
			return pageviews ? { pageviews: pageviews } : {};
		}, function () {
			// AQS returns a 404 when the page has 0 view. Even for real errors, it's
			// not worth replacing the task card with an error message just because we
			// could not put a pageview count on it.
			return {};
		} );
	};

	/**
	 * Update the HTML for the card with whatever is in this.currentCard.
	 */
	SuggestedEditsModule.prototype.updateCardElement = function () {
		var cardSelector = '.suggested-edits-card',
			$cardElement = $( cardSelector );
		$cardElement.html( this.currentCard.$element );
		this.setupClickLogging();
	};

	/**
	 * Update the control chrome around the card depending on the state of the queue and query.
	 */
	SuggestedEditsModule.prototype.updateControls = function () {
		this.setFilterQueriesFromDialogState();
		this.filters.toggle( true );
		this.filters.updateButtonLabelAndIcon( this.taskTypesQuery, this.topicsQuery || [] );
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
					this.getCardLogData( this.queuePosition ) );
			}.bind( this ) );
	};

	/**
	 * Set up the suggested edits module within the given container, fetch the tasks
	 * and display the first.
	 * @param {jQuery} $container
	 * @return {jQuery.Promise} Status promise.
	 */
	function initSuggestedTasks( $container ) {
		var suggestedEditsModule,
			savedTaskTypeFilters = mw.user.options.get( 'growthexperiments-homepage-se-filters' ),
			savedTopicFilters = mw.user.options.get( 'growthexperiments-homepage-se-topic-filters' ),
			taskTypes = savedTaskTypeFilters ?
				JSON.parse( savedTaskTypeFilters ) :
				initialTaskTypes,
			topicFilters = savedTopicFilters ? JSON.parse( savedTopicFilters ) : '',
			$wrapper = $container.find( '.suggested-edits-module-wrapper' ),
			mode = $wrapper.closest( '.growthexperiments-homepage-module' ).data( 'mode' );
		if ( !$wrapper.length ) {
			return;
		}
		suggestedEditsModule = new SuggestedEditsModule(
			{
				$element: $wrapper,
				taskTypePresets: taskTypes,
				topicPresets: topicFilters,
				topicMatching: mw.config.get( 'GEHomepageSuggestedEditsEnableTopics' ),
				mode: mode
			},
			new Logger(
				mw.config.get( 'wgGEHomepageLoggingEnabled' ),
				mw.config.get( 'wgGEHomepagePageviewToken' )
			),
			new GrowthTasksApi() );
		return suggestedEditsModule.fetchTasksAndUpdateView()
			.then( function () {
				suggestedEditsModule.filters.toggle( true );
				return suggestedEditsModule.showCard();
			} ).done( function () {
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
