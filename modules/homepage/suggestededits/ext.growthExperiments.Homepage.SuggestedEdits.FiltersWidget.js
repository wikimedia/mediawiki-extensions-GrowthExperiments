( function () {
	'use strict';
	var taskTypes = require( './TaskTypes.json' );

	/**
	 * @param {Object} config Configuration options
	 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
	 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
	 * @param {HomepageModuleLogger} logger
	 * @constructor
	 */
	function SuggestedEditsFiltersWidget( config, logger ) {
		var DifficultyFiltersDialog = require( './ext.growthExperiments.Homepage.SuggestedEdits.DifficultyFiltersDialog.js' ),
			windowManager = new OO.ui.WindowManager( { modal: true } );

		this.mode = config.mode;
		this.difficultyFilterButtonWidget = new OO.ui.ButtonWidget( {
			icon: 'difficulty-outline',
			indicator: config.mode === 'desktop' ? null : 'down'
		} );
		this.dialog = new DifficultyFiltersDialog( config )
			.on( 'done', function () {
				this.emit( 'done' );
			}.bind( this ) )
			.on( 'search', function ( search ) {
				this.emit( 'search', search );
			}.bind( this ) );

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
			items: [ this.difficultyFilterButtonWidget ]
		} ) );

	}

	OO.inheritClass( SuggestedEditsFiltersWidget, OO.ui.ButtonGroupWidget );

	SuggestedEditsFiltersWidget.prototype.updateMatchCount = function ( count ) {
		this.dialog.updateMatchCount( count );
	};

	/**
	 * Update the button label and icon depending on task types selected.
	 * @param {string[]} search
	 */
	SuggestedEditsFiltersWidget.prototype.updateButtonLabelAndIcon = function ( search ) {
		var levels = {},
			messages = [];

		if ( !search.length ) {
			// User has deselected all filters, set generic outline and message in button label.
			this.difficultyFilterButtonWidget.setLabel(
				mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text()
			);
			this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
			return;
		}

		search.forEach( function ( taskType ) {
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
