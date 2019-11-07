( function () {
	var EditCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEditCardWidget.js' ),
		EndOfQueueWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.EndOfQueueWidget.js' ),
		ErrorCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js' ),
		NoResultsWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.NoResultsWidget.js' ),
		TaskExplanationWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.TaskExplanationWidget.js' ),
		PagerWidget = require( './ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js' ),
		PreviousNextWidget = require( './ext.growthExperiments.Homepage.SuggestedEditsPreviousNextWidget.js' ),
		FiltersButtonGroupWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.FiltersWidget.js' ),
		SuggestedEditsModule = function ( config ) {
			var $pager, $previous, $next, $filters;
			SuggestedEditsModule.super.call( this, config );

			this.currentCard = null;
			this.apiPromise = null;

			this.filters = new FiltersButtonGroupWidget( { presets: config.taskTypePresets } )
				.connect( this, { search: 'fetchTasks' } )
				.toggle( false );
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

			$filters = this.$element.find( '.suggested-edits-filters' );
			if ( !$filters.length ) {
				$filters = $( '<div>' ).addClass( 'suggested-edits-filters' ).appendTo( this.$element );
			}

			$pager.append( this.pager.$element );
			$previous.append( this.previousWidget.$element );
			$next.append( this.nextWidget.$element );
			$filters.append( this.filters.$element );
		};

	OO.inheritClass( SuggestedEditsModule, OO.ui.Widget );

	/**
	 * Fetch suggested edits from ApiQueryGrowthTasks.
	 *
	 * @param {string[]} taskTypes
	 * @return {jQuery.Promise}
	 */
	SuggestedEditsModule.prototype.fetchTasks = function ( taskTypes ) {
		var apiParams = {
			action: 'query',
			prop: 'info|pageviews|pageimages',
			inprop: 'protection|url',
			pvipdays: 1,
			pithumbsize: 260,
			generator: 'growthtasks',
			// Fetch more in case protected articles are in the result set, so that after
			// filtering we can have 200.
			// TODO: Filter out protected articles on the server side.
			ggtlimit: 250,
			ggttasktypes: taskTypes.join( '|' ),
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLanguage' )
		};
		if ( this.apiPromise ) {
			this.apiPromise.abort();
		}
		this.currentCard = null;
		this.taskQueue = [];
		this.queuePosition = 0;
		if ( !taskTypes.length ) {
			// User has deselected all checkboxes; update the count and show
			// no results.
			this.filters.updateMatchCount( this.taskQueue.length );
			return this.showCard( new NoResultsWidget( { topicMatching: false } ) );
		}
		this.filters.updateButtonLabelAndIcon( taskTypes );
		this.apiPromise = new mw.Api().get( apiParams );
		return this.apiPromise.then( function ( data ) {
			function cleanUpData( item ) {
				return {
					thumbnailSource: item.thumbnail && item.thumbnail.source || null,
					title: item.title,
					url: item.canonicalurl,
					pageviews: item.pageviews &&
						item.pageviews[ Object.keys( item.pageviews )[ 0 ] ] || null,
					tasktype: item.tasktype,
					difficulty: item.difficulty
				};
			}
			function filterOutProtectedArticles( result ) {
				return result.protection.length === 0;
			}
			if ( data.growthtasks.totalCount > 0 ) {
				this.taskQueue = data.query.pages
					.filter( filterOutProtectedArticles )
					.map( cleanUpData )
					// Maximum number of tasks in the queue is always 200.
					.slice( 0, 200 );
			}
			this.filters.updateMatchCount( this.taskQueue.length );
			// use done instead of then so failed preloads will be retried when the
			// user navigates
			return this.showCard().done( function () {
				// Preload the next card's data.
				this.getExtractAndUpdateQueue( this.queuePosition + 1 );
			}.bind( this ) );
		}.bind( this ) ).catch( function ( error, details ) {
			if ( error === 'http' && details && details.textStatus === 'abort' ) {
				// Don't show error card for XHR abort.
				return;
			}
			this.showCard( new ErrorCardWidget() );
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

	SuggestedEditsModule.prototype.onNextCard = function () {
		this.queuePosition = this.queuePosition + 1;
		this.showCard();
		// Preload the next card's data.
		if ( this.taskQueue[ this.queuePosition + 1 ] &&
			!this.taskQueue[ this.queuePosition + 1 ].extract ) {
			this.getExtractAndUpdateQueue( this.queuePosition + 1 );
		}
	};

	SuggestedEditsModule.prototype.onPreviousCard = function () {
		this.queuePosition = this.queuePosition - 1;
		this.showCard();
	};

	SuggestedEditsModule.prototype.updateTaskExplanationWidget = function () {
		var explanationSelector = '.suggested-edits-task-explanation',
			$explanationElement = $( explanationSelector );
		if ( this.queuePosition < this.taskQueue.length ) {
			$explanationElement.html(
				new TaskExplanationWidget( this.taskQueue[ this.queuePosition ] ).$element
			);
			$explanationElement.toggle( true );
		} else {
			$explanationElement.toggle( false );
		}
	};

	SuggestedEditsModule.prototype.showCard = function ( card ) {
		var queuePosition = this.queuePosition,
			suggestedEditData = this.taskQueue[ queuePosition ];
		this.currentCard = null;
		if ( card ) {
			this.currentCard = card;
		} else if ( !this.taskQueue.length ) {
			this.currentCard = new NoResultsWidget( { topicMatching: false } );
		} else if ( !suggestedEditData ) {
			this.currentCard = new EndOfQueueWidget( { topicMatching: false } );
		}
		if ( this.currentCard ) {
			this.updateCardAndControlsPresentation();
			return;
		}

		return this.getExtractAndUpdateQueue( queuePosition ).done( function () {
			if ( queuePosition !== this.queuePosition ) {
				return;
			}
			this.currentCard = new EditCardWidget( this.taskQueue[ queuePosition ] );
			this.updateCardAndControlsPresentation();
		}.bind( this ) );
	};

	SuggestedEditsModule.prototype.getExtractAndUpdateQueue = function ( taskQueuePosition ) {
		var apiUrl = mw.config.get( 'wgGERestbaseUrl' ) + '/page/summary/',
			suggestedEditData = this.taskQueue[ taskQueuePosition ];
		if ( suggestedEditData && suggestedEditData.extract ) {
			return $.Deferred().resolve().promise();
		}

		return $.get( apiUrl + encodeURI( suggestedEditData.title ) ).done( function ( data ) {
			suggestedEditData.extract = data.extract;
			if ( !suggestedEditData.thumbnailSource && data.thumbnail ) {
				// This will only apply for some beta wiki configurations and local setups.
				suggestedEditData.thumbnailSource = data.thumbnail.source;
			}
			// Update the suggested edit data so we don't need to fetch it again
			// if the user views the card more than once.
			this.taskQueue[ taskQueuePosition ] = suggestedEditData;
		}.bind( this ) );
	};

	SuggestedEditsModule.prototype.updateCardAndControlsPresentation = function () {
		var cardSelector = '.suggested-edits-card',
			$cardElement = $( cardSelector );
		$cardElement.html( this.currentCard.$element );
		this.filters.toggle( true );
		this.updatePager();
		this.updatePreviousNextButtons();
		this.updateTaskExplanationWidget();
	};

	function initSuggestedTasks( $container ) {
		var suggestedEditsModule,
			savedTaskTypeFilters = mw.user.options.get( 'growthexperiments-homepage-se-filters' ),
			taskTypes = savedTaskTypeFilters ? JSON.parse( savedTaskTypeFilters ) : [ 'copyedit', 'links' ],
			$wrapper = $container.find( '.suggested-edits-module-wrapper' );
		if ( !$wrapper.length ) {
			return;
		}
		suggestedEditsModule = new SuggestedEditsModule( { $element: $wrapper,
			taskTypePresets: taskTypes
		} );
		suggestedEditsModule.fetchTasks( taskTypes );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js
	// eslint-disable-next-line no-jquery/no-global-selector
	initSuggestedTasks( $( '.growthexperiments-homepage-container' ) );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'suggested-edits' ) {
			initSuggestedTasks( $content );
		}
	} );
}() );
