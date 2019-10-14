( function () {
	var EditCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEditCardWidget.js' ),
		EndOfQueueWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.EndOfQueueWidget.js' ),
		NoResultsWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.NoResultsWidget.js' ),
		TaskExplanationWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.TaskExplanationWidget.js' ),
		PagerWidget = require( './ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js' ),
		PreviousNextWidget = require( './ext.growthExperiments.Homepage.SuggestedEditsPreviousNextWidget.js' ),
		suggestedEditsModule,
		SuggestedEditsModule = function ( config ) {
			var pagerSelector = '.suggested-edits-pager',
				previousSelector = '.suggested-edits-previous',
				nextSelector = '.suggested-edits-next';
			SuggestedEditsModule.super.call( this, config );
			this.currentCard = null;
			this.pager = new PagerWidget().toggle( false );
			this.previousWidget = new PreviousNextWidget( { direction: 'Previous' } )
				.connect( this, { click: 'onPreviousCard' } )
				.toggle( false );
			this.nextWidget = new PreviousNextWidget( { direction: 'Next' } )
				.connect( this, { click: 'onNextCard' } )
				.toggle( false );
			this.taskQueue = [];
			this.queuePosition = 0;
			$( pagerSelector ).append( this.pager.$element );
			$( previousSelector ).append( this.previousWidget.$element );
			$( nextSelector ).append( this.nextWidget.$element );
		};

	OO.inheritClass( SuggestedEditsModule, OO.ui.Widget );

	SuggestedEditsModule.prototype.fetchTasks = function () {
		this.taskQueue = [];
		new mw.Api().get( {
			action: 'query',
			prop: 'info|pageviews|extracts|pageimages',
			inprop: 'protection|url',
			pvipdays: 1,
			explaintext: 1,
			exintro: 1,
			pithumbsize: 260,
			generator: 'growthtasks',
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLang' )
		} ).done( function ( data ) {
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
			this.showCard();
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
		this.updatePager();
		this.updatePreviousNextButtons();
		this.updateTaskExplanationWidget();
	};

	suggestedEditsModule = new SuggestedEditsModule();
	suggestedEditsModule.fetchTasks();

}() );
