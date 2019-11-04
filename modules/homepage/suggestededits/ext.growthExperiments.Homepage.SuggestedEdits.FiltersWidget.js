( function () {
	'use strict';

	function SuggestedEditsFiltersWidget( config ) {
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

		this.dialog.$element.addClass( 'suggested-edits-difficulty-filters' );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ this.dialog ] );
		this.difficultyFilterButtonWidget.on( 'click', function () {
			windowManager.openWindow( this.dialog );
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
