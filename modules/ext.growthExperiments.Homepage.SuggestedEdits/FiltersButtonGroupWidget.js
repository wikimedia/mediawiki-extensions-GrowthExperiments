'use strict';

var TaskTypesAbFilter = require( './TaskTypesAbFilter.js' ),
	TOPIC_MATCH_MODES = require( './constants.js' ).TOPIC_MATCH_MODES,
	taskTypes = TaskTypesAbFilter.getTaskTypes(),
	topicData = require( './Topics.js' );

/**
 * @extends OO.ui.ButtonGroupWidget
 *
 * @param {Object} config Configuration options
 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
 * @param {mw.libs.ge.TopicFilters|null} config.topicPresets Object containing selected topic filters
 * @param {boolean} config.topicMatching If the topic filters should be enabled in the UI.
 * @param {boolean} config.useTopicMatchMode If topic match mode feature is enabled in the UI
 * @param {string} config.mode Rendering mode. See constants in IDashboardModule.php
 * @param {HomepageModuleLogger} logger
 * @constructor
 */
function FiltersButtonGroupWidget( config, logger ) {
	var DifficultyFiltersDialog =
			require( './DifficultyFiltersDialog.js' ),
		TopicFiltersDialog = require( './TopicFiltersDialog.js' ),
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
			presets: this.topicPresets,
			useTopicMatchMode: config.useTopicMatchMode
		} ).connect( this, {
			done: function ( promise ) {
				this.emit( 'done', promise );
			},
			search: function () {
				this.emit( 'search' );
			},
			toggleMatchMode: function ( matchMode ) {
				logger.log(
					'suggested-edits',
					config.mode,
					// Possible event names are:
					// 'se-topicmatchmode-or'
					// 'se-topicmatchmode-and'
					'se-topicmatchmode-' + matchMode.toLowerCase(),
					{
						topicsMatchMode: matchMode
					}
				);
			},
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
		classes: this.topicMatching ? [ 'topic-matching', 'difficulty-filter-button' ] : [ '' ],
		indicator: config.mode === 'desktop' ? null : 'down'
	} );
	buttonWidgets.push( this.difficultyFilterButtonWidget );

	this.taskTypeFiltersDialog = new DifficultyFiltersDialog( {
		presets: config.taskTypePresets
	} ).connect( this, {
		done: function ( promise ) {
			this.emit( 'done', promise );
		},
		search: function () {
			this.emit( 'search' );
		},
		cancel: function () {
			this.emit( 'cancel' );
		}
	} );
	windows.push( this.taskTypeFiltersDialog );

	this.taskTypeFiltersDialog.$element
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
			logger.log( 'suggested-edits', config.mode, 'se-topicfilter-open', {
				topics: this.topicFiltersDialog.getEnabledFilters().getTopics()
			} );
			if ( config.useTopicMatchMode ) {
				logger.log( 'suggested-edits', config.mode, 'se-topicmatchmode-impression' );
			}
			this.emit( 'open' );
			lifecycle.closing.done( function ( data ) {
				var closeExtraData = {
					topics: this.topicFiltersDialog.getEnabledFilters().getTopics()
				};
				if ( config.useTopicMatchMode ) {
					closeExtraData.topicsMatchMode = this.topicFiltersDialog.getEnabledFilters()
						.getTopicsMatchMode();
				}
				if ( data && data.action === 'done' ) {
					logger.log( 'suggested-edits', config.mode, 'se-topicfilter-done', closeExtraData );
				} else {
					logger.log( 'suggested-edits', config.mode, 'se-topicfilter-cancel', closeExtraData );
				}
			}.bind( this ) );
		}.bind( this ) );
	}

	FiltersButtonGroupWidget.super.call( this, $.extend( {}, config, {
		items: buttonWidgets
	} ) );

}

OO.inheritClass( FiltersButtonGroupWidget, OO.ui.ButtonGroupWidget );

/**
 * Update the article count in FiltersDialog, called from SuggestedEditsModule when the
 * article count changes when user selects a filter or cancels from FiltersDialog
 *
 * @param {number} count
 */
FiltersButtonGroupWidget.prototype.updateMatchCount = function ( count ) {
	this.taskTypeFiltersDialog.updateMatchCount( count );
	if ( this.topicFiltersDialog ) {
		this.topicFiltersDialog.updateMatchCount( count );
	}
};

/**
 * Update the button label and icon depending on task types selected.
 *
 * Keep this function in sync with HomepageModules\SuggestedEdits::getFiltersButtonGroupWidget()
 *
 * @param {string[]} taskTypeSearch List of task types to search for
 * @param {mw.libs.ge.TopicFilters} topicSearch TopicFilters object with list
 * of topics to search for and match mode
 */
FiltersButtonGroupWidget.prototype.updateButtonLabelAndIcon = function (
	taskTypeSearch, topicSearch
) {
	var levels = {},
		topicMessages = [],
		topicLabel = '',
		separator = '',
		isMatchModeAND = topicSearch &&
			topicSearch.getTopicsMatchMode() === TOPIC_MATCH_MODES.AND,
		messages = [];

	if ( this.topicFilterButtonWidget ) {
		if ( !topicSearch.hasFilters() ) {
			this.topicFilterButtonWidget.setLabel(
				mw.message( 'growthexperiments-homepage-suggestededits-topic-filter-select-interests' ).text()
			);
			// topicPresets will be a TopicFilters object if the user had saved topics
			// in the past, or null if they have never saved topics
			this.topicFilterButtonWidget.setFlags( { progressive: !this.topicPresets } );
		} else {
			topicSearch.getTopics().forEach( function ( topic ) {
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
				separator = isMatchModeAND ? ' + ' : mw.msg( 'comma-separator' );
				topicLabel = topicMessages.join( separator );
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

module.exports = FiltersButtonGroupWidget;
