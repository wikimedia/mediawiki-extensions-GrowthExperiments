( function () {
	'use strict';

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

		this.difficultyFilterButtonWidget = new OO.ui.ButtonWidget( {
			icon: 'difficulty-outline'
		} );
		this.dialog = new DifficultyFiltersDialog( config )
			.on( 'search', function ( search ) {
				this.emit( 'search', search );
				this.updateButtonLabelAndIcon( search );
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
		var groups = [];
		if ( !search.length ) {
			// User has deselected all filters, set generic outline and message in button label.
			this.difficultyFilterButtonWidget.setLabel(
				mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-title' ).text()
			);
			this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
			return;
		}
		search.forEach( function ( taskType ) {
			function addMessage( messages, difficultyLevel ) {
				// growthexperiments-homepage-suggestededits-difficulty-filter-label-easy
				// growthexperiments-homepage-suggestededits-difficulty-filter-label-medium
				// growthexperiments-homepage-suggestededits-difficulty-filter-label-hard
				var label = mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-label-' + difficultyLevel ).text();
				if ( messages.indexOf( label ) === -1 ) {
					messages.push( label );
				}
				return messages;
			}
			if ( [ 'links', 'copyedit' ].indexOf( taskType ) > -1 ) {
				groups = addMessage( groups, 'easy' );
				this.difficultyFilterButtonWidget.setIcon( 'difficulty-easy' );
			}
			if ( [ 'references', 'update' ].indexOf( taskType ) > -1 ) {
				groups = addMessage( groups, 'medium' );
				this.difficultyFilterButtonWidget.setIcon( 'difficulty-medium' );
			}
			if ( [ 'expand' ].indexOf( taskType ) > -1 ) {
				groups = addMessage( groups, 'hard' );
				this.difficultyFilterButtonWidget.setIcon( 'difficulty-hard' );
			}
		}.bind( this ) );

		if ( groups.length > 1 ) {
			this.difficultyFilterButtonWidget.setIcon( 'difficulty-outline' );
		}

		this.difficultyFilterButtonWidget.setLabel(
			mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-label' )
				.params( [ groups.join( mw.msg( 'comma-separator' ) ) ] )
				.text()
		);
	};

	module.exports = SuggestedEditsFiltersWidget;
}() );
