( function () {
	'use strict';

	function SuggestedEditsFiltersWidget( config ) {
		var DifficultyFiltersDialog = require( './ext.growthExperiments.Homepage.SuggestedEdits.DifficultyFiltersDialog.js' ),
			windowManager = new OO.ui.WindowManager( { modal: true } );

		this.difficultyFilterButtonWidget = new OO.ui.ButtonWidget( {
			icon: 'difficulty-outline'
		} );
		this.dialog = new DifficultyFiltersDialog()
			.on( 'search', function ( search ) {
				this.emit( 'search', search );
				this.updateButtonLabel( search );
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

	SuggestedEditsFiltersWidget.prototype.updateButtonLabel = function ( search ) {
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
			}
			if ( [ 'references', 'update' ].indexOf( taskType ) > -1 ) {
				groups = addMessage( groups, 'medium' );
			}
			if ( [ 'expand' ].indexOf( taskType ) > -1 ) {
				groups = addMessage( groups, 'hard' );
			}
		} );
		this.difficultyFilterButtonWidget.setLabel(
			mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filter-label' )
				.params( [ groups.join( mw.msg( 'comma-separator' ) ) ] )
				.text()
		);
	};

	module.exports = SuggestedEditsFiltersWidget;
}() );
