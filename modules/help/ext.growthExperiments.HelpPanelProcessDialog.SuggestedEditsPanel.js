( function () {
	'use strict';

	var suggestedEditsPeek = require( '../helppanel/ext.growthExperiments.SuggestedEditsPeek.js' );

	/**
	 * @param {Object} config The standard config to pass to OO.ui.PanelLayout,
	 *  plus configuration specific to building the suggested edits panel.
	 * @param {Object} config.taskTypeData The data for a particular task.
	 * @constructor
	 */
	function SuggestedEditsPanel( config ) {
		SuggestedEditsPanel.super.call( this, config );
		this.config = config;
		if ( !config.taskTypeData ) {
			return;
		}
		this.taskTypeData = config.taskTypeData;
		this.build();
	}

	OO.inheritClass( SuggestedEditsPanel, OO.ui.PanelLayout );

	/**
	 * Appends the header, quickstart tips, and footer to the panel.
	 */
	SuggestedEditsPanel.prototype.build = function () {
		this.$element.addClass( 'suggested-edits-panel' );
		this.$element.append(
			this.getHeader(),
			this.getQuickStartTips()
		);
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
	 * Get the quick start tips for the panel.
	 *
	 * FIXME: Building quick start tips might be complicated enough to move
	 * into another file.
	 *
	 * @return {jQuery}
	 */
	SuggestedEditsPanel.prototype.getQuickStartTips = function () {
		return $( '<h4>' ).addClass( 'suggested-edits-panel-quick-start-tips' )
			.text( mw.message( 'growthexperiments-help-panel-suggestededits-quick-start-tips' ).text() );
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
