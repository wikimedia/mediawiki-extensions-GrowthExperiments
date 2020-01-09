( function () {
	'use strict';
	var taskTypes = require( './TaskTypes.json' );

	/**
	 * @param {Object} config Configuration options
	 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
	 * @param {Array<string>} config.topicPresets List of IDs of enabled topic filters
	 * @param {bool} config.topicMatching If the topic filters should be enabled in the UI.
	 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
	 * @param {HomepageModuleLogger} logger
	 * @constructor
	 */
	function SuggestedEditsFiltersWidget( config, logger ) {
		var DifficultyFiltersDialog = require( './ext.growthExperiments.Homepage.SuggestedEdits.DifficultyFiltersDialog.js' ),
			windowManager = new OO.ui.WindowManager( { modal: true } ),
			buttonWidgets = [];

		this.mode = config.mode;
		this.topicMatching = config.topicMatching;

		if ( this.topicMatching ) {
			this.topicFilterButtonWidget = new OO.ui.ButtonWidget( {
				icon: 'funnel',
				classes: [ 'topic-matching' ],
				indicator: config.mode === 'desktop' ? null : 'down'
			} );
			buttonWidgets.push( this.topicFilterButtonWidget );
		}

		this.difficultyFilterButtonWidget = new OO.ui.ButtonWidget( {
			icon: 'difficulty-outline',
			classes: [ this.topicMatching ? 'topic-matching' : '' ],
			indicator: config.mode === 'desktop' ? null : 'down'
		} );
		buttonWidgets.push( this.difficultyFilterButtonWidget );

		this.dialog = new DifficultyFiltersDialog( {
			presets: config.taskTypePresets
		} ).connect( this, {
			done: function () {
				this.emit( 'done' );
			},
			search: function ( search ) {
				this.emit( 'search', search );
			}
		} );

		this.dialog.$element.addClass( 'suggested-edits-difficulty-filters' )
			.on( 'click', '.suggested-edits-create-article-additional-msg a', function () {
				logger.log( 'suggested-edits', config.mode, 'link-click',
					{ linkId: 'se-create-info' } );
			} );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ this.dialog ] );
		this.difficultyFilterButtonWidget.on( 'click', function () {
			var lifecycle = windowManager.openWindow( this.dialog );
			logger.log( 'suggested-edits', config.mode, 'se-taskfilter-open' );
			lifecycle.closing.done( function ( data ) {
				if ( data && data.action === 'done' ) {
					logger.log( 'suggested-edits', config.mode, 'se-taskfilter-done',
						{ taskTypes: this.dialog.getEnabledFilters() } );
				} else {
					logger.log( 'suggested-edits', config.mode, 'se-taskfilter-cancel',
						{ taskTypes: this.dialog.getEnabledFilters() } );
				}
			}.bind( this ) );
		}.bind( this ) );

		SuggestedEditsFiltersWidget.super.call( this, $.extend( {}, config, {
			items: buttonWidgets
		} ) );

	}

	OO.inheritClass( SuggestedEditsFiltersWidget, OO.ui.ButtonGroupWidget );

	SuggestedEditsFiltersWidget.prototype.updateMatchCount = function ( count ) {
		this.dialog.updateMatchCount( count );
	};

	/**
	 * Update the button label and icon depending on task types selected.
	 * @param {string[]} taskTypeSearch List of task types to search for
	 * @param {string[]} topicSearch List of topics to search for
	 */
	SuggestedEditsFiltersWidget.prototype.updateButtonLabelAndIcon = function (
		taskTypeSearch, topicSearch
	) {
		var levels = {},
			messages = [];

		if ( !topicSearch.length ) {
			this.topicFilterButtonWidget.setLabel(
				mw.message( 'growthexperiments-homepage-suggestededits-topic-filter-select-interests' ).text()
			);
			this.topicFilterButtonWidget.setFlags( [ 'progressive' ] );
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
			// growthexperiments-homepage-suggestededits-difficulty-filter-label-easy
			// growthexperiments-homepage-suggestededits-difficulty-filter-label-medium
			// growthexperiments-homepage-suggestededits-difficulty-filter-label-hard
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
