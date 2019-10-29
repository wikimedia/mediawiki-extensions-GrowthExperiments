( function () {
	var EditCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEditCardWidget.js' ),
		EndOfQueueWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.EndOfQueueWidget.js' ),
		NoResultsWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.NoResultsWidget.js' ),
		TaskExplanationWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.TaskExplanationWidget.js' ),
		PagerWidget = require( './ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js' ),
		PreviousNextWidget = require( './ext.growthExperiments.Homepage.SuggestedEditsPreviousNextWidget.js' ),
		FiltersButtonGroupWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.FiltersWidget.js' ),
		SuggestedEditsModule = function ( config ) {
			var $pager, $previous, $next, $filters;
			SuggestedEditsModule.super.call( this, config );

			this.currentCard = null;

			this.filters = new FiltersButtonGroupWidget()
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
	 * If initContext is true, then don't save the task types to the user's preferences.
	 *
	 * @param {string[]} taskTypes
	 * @param {bool} initContext
	 * @return {jQuery.Promise}
	 */
	SuggestedEditsModule.prototype.fetchTasks = function ( taskTypes, initContext ) {
		var apiParams = {
			action: 'query',
			prop: 'info|pageviews|extracts|pageimages',
			inprop: 'protection|url',
			pvipdays: 1,
			explaintext: 1,
			exintro: 1,
			pithumbsize: 260,
			generator: 'growthtasks',
			ggttasktypes: taskTypes.join( '|' ),
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLang' )
		};
		this.currentCard = null;
		this.taskQueue = [];
		this.queuePosition = 0;
		this.filters.updateButtonLabel( taskTypes );
		return new mw.Api().get( apiParams ).done( function ( data ) {
			function cleanUpData( item ) {
				return {
					thumbnailSource: item.thumbnail && item.thumbnail.source || null,
					title: item.title,
					extract: item.extract,
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
					.map( cleanUpData );
			}
			this.filters.updateMatchCount( this.taskQueue.length );
			this.showCard();
			if ( !initContext ) {
				this.savePreferences( taskTypes );
			}
		}.bind( this ) );
	};

	SuggestedEditsModule.prototype.savePreferences = function ( taskTypes ) {
		return new mw.Api().saveOption( 'growthexperiments-homepage-se-filters', JSON.stringify( taskTypes ) );
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

	SuggestedEditsModule.prototype.showCard = function () {
		var suggestedEditData = this.taskQueue[ this.queuePosition ],
			cardSelector = '.suggested-edits-card',
			$cardElement = $( cardSelector );

		if ( !this.taskQueue.length ) {
			this.currentCard = new NoResultsWidget( { topicMatching: false } );
		} else if ( !suggestedEditData ) {
			this.currentCard = new EndOfQueueWidget( { topicMatching: false } );
		} else {
			this.currentCard = new EditCardWidget( suggestedEditData );
		}

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
		suggestedEditsModule = new SuggestedEditsModule( { $element: $wrapper } );
		suggestedEditsModule.fetchTasks( taskTypes, true );
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
