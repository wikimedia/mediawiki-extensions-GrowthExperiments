( function () {
	'use strict';

	var suggestedEditsPeek = require( '../helppanel/ext.growthExperiments.SuggestedEditsPeek.js' ),
		quickStartTips = require( '../helppanel/ext.growthExperiments.SuggestedEdits.QuickStartTips.js' );

	/**
	 * @param {Object} config The standard config to pass to OO.ui.PanelLayout,
	 *  plus configuration specific to building the suggested edits panel.
	 * @param {Object} config.taskTypeData The data for a particular task.
	 * @param {boolean} config.guidanceEnabled If guidance is available for this user and task type.
	 * @param {string} config.editorInterface The editor interface in use
	 * @constructor
	 */
	function SuggestedEditsPanel( config ) {
		SuggestedEditsPanel.super.call( this, config );
		this.config = config;
		if ( !config.taskTypeData || !config.guidanceEnabled ) {
			return;
		}
		this.editorInterface = config.editorInterface;
		this.taskTypeData = config.taskTypeData;
		this.build();
	}

	OO.inheritClass( SuggestedEditsPanel, OO.ui.StackLayout );

	/**
	 * Appends the header, quickstart tips, and footer to the panel.
	 */
	SuggestedEditsPanel.prototype.build = function () {
		this.$element.addClass( 'suggested-edits-panel' );
		quickStartTips.getTips( this.taskTypeData.id, this.editorInterface ).then( function ( tips ) {
			this.addItems( [
				new OO.ui.PanelLayout( {
					padded: false,
					expanded: true,
					$content: this.getHeader()
				} ),
				new OO.ui.PanelLayout( {
					padded: true,
					expanded: true,
					$content: tips
				} )
			] );
		}.bind( this ) );
	};

	/**
	 * Get the suggested edits peek header content.
	 *
	 * @return {jQuery}
	 */
	SuggestedEditsPanel.prototype.getHeader = function () {
		return suggestedEditsPeek.getSuggestedEditsPeek(
			'suggested-edits-panel-header',
			this.taskTypeData.messages,
			this.taskTypeData.difficulty
		);
	};

	/**
	 * Get the footer content for the panel.
	 *
	 * Note, the content from this method is appended to the bottom of the ProcessDialog's
	 * $body, and not to the suggested edits PanelLayout.
	 *
	 * @return {jQuery}
	 */
	SuggestedEditsPanel.prototype.getFooter = function () {
		return $( '<footer>' ).addClass( 'suggested-edits-panel-footer' ).append(
			$( '<div>' ).addClass( 'suggested-edits-panel-footer-text' )
				// The following messages are used here:
				// * growthexperiments-help-panel-suggestededits-footer-mobile
				// * growthexperiments-help-panel-suggestededits-footer-desktop
				.html( mw.message( 'growthexperiments-help-panel-suggestededits-footer-' +
					( OO.ui.isMobile() ? 'mobile' : 'desktop' ) ).parse() ) );
	};

	module.exports = SuggestedEditsPanel;

}() );
