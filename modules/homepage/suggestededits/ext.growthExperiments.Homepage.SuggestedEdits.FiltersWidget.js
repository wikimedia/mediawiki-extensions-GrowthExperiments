/**
 * @external HomepageModuleLogger
 */
( function () {
	'use strict';
	var taskTypes = require( './TaskTypes.json' ),
		topicData = require( 'ext.growthExperiments.Homepage.Topics' ).topics;

	/**
	 * @param {Object} config Configuration options
	 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
	 * @param {Array<string>} config.topicPresets List of IDs of enabled topic filters
	 * @param {boolean} config.topicMatching If the topic filters should be enabled in the UI.
	 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
	 * @param {HomepageModuleLogger} logger
	 * @constructor
	 */
	function SuggestedEditsFiltersWidget( config, logger ) {
		var DifficultyFiltersDialog =
				require( './ext.growthExperiments.Homepage.SuggestedEdits.DifficultyFiltersDialog.js' ),
			TopicFiltersDialog = require( './ext.growthExperiments.Homepage.SuggestedEdits.TopicFiltersDialog.js' ),
			windowManager = new OO.ui.WindowManager( { modal: true } ),
			windows = [],
			buttonWidgets = [];

		this.mode = config.mode;
		this.topicMatching = config.topicMatching;
		this.topicPresets = config.topicPresets;

		if ( this.topicMatching ) {
			this.topicFilterButtonWidget = new OO.ui.ButtonWidget( {
				icon: 'funnel',
				classes: [ 'topic-matching', 'topic-filter-button' ],
				indicator: config.mode === 'desktop' ? null : 'down'
			} );
			buttonWidgets.push( this.topicFilterButtonWidget );
			this.topicFiltersDialog = new TopicFiltersDialog( {
				presets: config.topicPresets
			} ).connect( this, {
				done: [ 'emit', 'done' ],
				// forwards one argument, the list of enabled filters
				search: [ 'emit', 'search' ],
				selectAll: function ( groupId ) {
					logger.log( 'suggested-edits', config.mode, 'se-topicfilter-select-all', {
						isCta: false,
						topicGroup: groupId
					} );
				},
				removeAll: function ( groupId ) {
					logger.log( 'suggested-edits', config.mode, 'se-topicfilter-remove-all', {
						isCta: false,
						topicGroup: groupId
					} );
				},
				cancel: [ 'emit', 'cancel' ]
			} );
			this.topicFiltersDialog.$element.addClass( 'suggested-edits-topic-filters' );
			windows.push( this.topicFiltersDialog );
		}

		this.difficultyFilterButtonWidget = new OO.ui.ButtonWidget( {
			icon: 'difficulty-outline',
			classes: [ this.topicMatching ? 'topic-matching' : '' ],
			indicator: config.mode === 'desktop' ? null : 'down'
		} );
		buttonWidgets.push( this.difficultyFilterButtonWidget );

		this.taskTypeFiltersDialog = new DifficultyFiltersDialog( {
			presets: config.taskTypePresets
		} ).connect( this, {
			done: function () {
				this.emit( 'done' );
			},
			search: function ( search ) {
				this.emit( 'search', search );
			},
			cancel: function () {
				this.emit( 'cancel' );
			}
		} );
		windows.push( this.taskTypeFiltersDialog );

		this.taskTypeFiltersDialog.$element.addClass( 'suggested-edits-difficulty-filters' )
			.on( 'click', '.suggested-edits-create-article-additional-msg a', function () {
				logger.log( 'suggested-edits', config.mode, 'link-click',
					{ linkId: 'se-create-info' } );
			} );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( windows );
		this.difficultyFilterButtonWidget.on( 'click', function () {
			var lifecycle = windowManager.openWindow( this.taskTypeFiltersDialog );
			logger.log( 'suggested-edits', config.mode, 'se-taskfilter-open' );
			this.emit( 'open' );
			lifecycle.closing.done( function ( data ) {
				if ( data && data.action === 'done' ) {
					logger.log( 'suggested-edits', config.mode, 'se-taskfilter-done',
						{ taskTypes: this.taskTypeFiltersDialog.getEnabledFilters() } );
				} else {
					logger.log( 'suggested-edits', config.mode, 'se-taskfilter-cancel',
						{ taskTypes: this.taskTypeFiltersDialog.getEnabledFilters() } );
				}
			}.bind( this ) );
		}.bind( this ) );

		if ( this.topicFilterButtonWidget ) {
			this.topicFilterButtonWidget.on( 'click', function () {
				var lifecycle = windowManager.openWindow( this.topicFiltersDialog );
				logger.log( 'suggested-edits', config.mode, 'se-topicfilter-open',
					{ topics: this.topicFiltersDialog.getEnabledFilters() } );
				this.emit( 'open' );
				lifecycle.closing.done( function ( data ) {
					if ( data && data.action === 'done' ) {
						logger.log( 'suggested-edits', config.mode, 'se-topicfilter-done', {
							topics: this.topicFiltersDialog.getEnabledFilters()
						} );
					} else {
						logger.log( 'suggested-edits', config.mode, 'se-topicfilter-cancel',
							{ topics: this.topicFiltersDialog.getEnabledFilters() } );
					}
				}.bind( this ) );
			}.bind( this ) );
		}

		SuggestedEditsFiltersWidget.super.call( this, $.extend( {}, config, {
			items: buttonWidgets
		} ) );

	}

	OO.inheritClass( SuggestedEditsFiltersWidget, OO.ui.ButtonGroupWidget );

	SuggestedEditsFiltersWidget.prototype.updateMatchCount = function ( count ) {
		this.taskTypeFiltersDialog.updateMatchCount( count );
		if ( this.topicFiltersDialog ) {
			this.topicFiltersDialog.updateMatchCount( count );
		}
	};

	/**
	 * Update the button label and icon depending on task types selected.
	 *
	 * @param {string[]} taskTypeSearch List of task types to search for
	 * @param {string[]} topicSearch List of topics to search for
	 */
	SuggestedEditsFiltersWidget.prototype.updateButtonLabelAndIcon = function (
		taskTypeSearch, topicSearch
	) {
		var levels = {},
			topicMessages = [],
			topicLabel = '',
			messages = [];

		if ( this.topicFilterButtonWidget ) {
			if ( !topicSearch.length ) {
				this.topicFilterButtonWidget.setLabel(
					mw.message( 'growthexperiments-homepage-suggestededits-topic-filter-select-interests' ).text()
				);
				// topicPresets will be an empty array if the user had saved topics
				// in the past.
				this.topicFilterButtonWidget.setFlags( { progressive: !this.topicPresets } );
			} else {
				topicSearch.forEach( function ( topic ) {
					if ( topicData[ topic ] && topicData[ topic ].name ) {
						topicMessages.push( topicData[ topic ].name );
					}
				} );
				// Unset the pulsating blue dot if it exists.
				this.topicFilterButtonWidget.$element.find( '.mw-pulsating-dot' ).remove();
				this.topicFilterButtonWidget.setFlags( { progressive: false } );
			}
			if ( topicMessages.length ) {
				if ( topicMessages.length < 3 ) {
					topicLabel = topicMessages.join( mw.msg( 'comma-separator' ) );
				} else {
					topicLabel = mw.message(
						'growthexperiments-homepage-suggestededits-topics-button-topic-count'
					).params( [ mw.language.convertNumber( topicMessages.length ) ] )
						.text();
				}
				this.topicFilterButtonWidget.setLabel( topicLabel );
			}
		}

		if ( !taskTypeSearch.length ) {
			// User has deselected all filters, set generic outline and message in button label.
			this.difficultyFilterButtonWidget.setLabel(
				mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text()
			);
			this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
			return;
		}

		taskTypeSearch.forEach( function ( taskType ) {
			levels[ taskTypes[ taskType ].difficulty ] = true;
		} );
		[ 'easy', 'medium', 'hard' ].forEach( function ( level ) {
			var label;
			if ( !levels[ level ] ) {
				return;
			}
			// The following messages are used here:
			// * growthexperiments-homepage-suggestededits-difficulty-filter-label-easy
			// * growthexperiments-homepage-suggestededits-difficulty-filter-label-medium
			// * growthexperiments-homepage-suggestededits-difficulty-filter-label-hard
			label = mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-label-' +
				level ).text();
			messages.push( label );
			// Icons: difficulty-easy, difficulty-medium, difficulty-hard
			this.difficultyFilterButtonWidget.setIcon( 'difficulty-' + level );
		}.bind( this ) );
		if ( messages.length > 1 ) {
			this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
		}

		this.difficultyFilterButtonWidget.setLabel(
			mw.message( this.mode === 'desktop' ?
				'growthexperiments-homepage-suggestededits-difficulty-filter-label' :
				'growthexperiments-homepage-suggestededits-difficulty-filter-label-mobile'
			)
				.params( [ messages.join( mw.msg( 'comma-separator' ) ) ] )
				.text()
		);
	};

	module.exports = SuggestedEditsFiltersWidget;
}() );
