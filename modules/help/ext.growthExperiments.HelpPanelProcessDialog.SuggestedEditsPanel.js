( function () {
	'use strict';

	var suggestedEditsPeek = require( '../helppanel/ext.growthExperiments.SuggestedEditsPeek.js' ),
		quickStartTips = require( '../helppanel/ext.growthExperiments.SuggestedEdits.QuickStartTips.js' );

	/**
	 * Create the suggested edit panel. The panel is initially empty; the code creating it
	 * must call SuggestedEditsPanel.build() to populate it.
	 * @param {Object} config The standard config to pass to OO.ui.PanelLayout,
	 *  plus configuration specific to building the suggested edits panel.
	 * @param {Object} config.taskTypeData The data for a particular task.
	 * @param {boolean} config.guidanceEnabled If guidance is available for this user and task type.
	 * @param {string} config.editorInterface The editor interface in use
	 * @param {string} config.currentTip The tip to preselect in the quick tips section.
	 * @constructor
	 */
	function SuggestedEditsPanel( config ) {
		SuggestedEditsPanel.super.call( this, $.extend( {
			expanded: false,
			scrollable: false,
			continuous: true
		}, config ) );
		this.config = config;
		if ( !config.taskTypeData || !config.guidanceEnabled ) {
			return;
		}
		this.editorInterface = config.editorInterface;
		this.taskTypeData = config.taskTypeData;
		this.currentTip = config.currentTip;
		/** @var {OO.ui.StackLayout} */
		this.tipsPanel = null;
	}

	OO.inheritClass( SuggestedEditsPanel, OO.ui.StackLayout );

	/**
	 * Appends the header, quickstart tips, and footer to the panel.
	 * @return {jQuery.Promise<boolean>} A promise that resolves with true when the tips are loaded.
	 *   The promise rejects when the tips failed to load. It resolves with false when the tips
	 *   did not need to be loaded (ie. the help panel should not contain guidance).
	 */
	SuggestedEditsPanel.prototype.build = function () {
		if ( !this.taskTypeData ) {
			return $.Deferred().resolve().promise( false );
		}

		this.$element.addClass( 'suggested-edits-panel suggested-edits-panel-with-footer' );
		this.footerPanel = new OO.ui.PanelLayout( {
			// Padding is set on the text itself in CSS
			padded: false,
			expanded: false,
			classes: [ 'suggested-edits-panel-footer' ],
			$content: this.getFooter()
		} );
		this.headerAndTipsPanel = new OO.ui.StackLayout( {
			padded: false,
			expanded: false,
			continuous: true,
			scrollable: true,
			classes: [ 'suggested-edits-panel-headerAndTips' ]
		} );
		this.headerPanel = new OO.ui.PanelLayout( {
			padded: false,
			expanded: false,
			$content: this.getHeader()
		} );
		return quickStartTips.getTips( this.taskTypeData.id, this.editorInterface, this.currentTip ).then( function ( tipsPanel ) {
			this.headerAndTipsPanel.addItems( [ this.headerPanel, tipsPanel ] );
			this.addItems( [ this.headerAndTipsPanel, this.footerPanel ] );
			// Used by the auto-advance logic in HelpPanelProcessDialog
			this.tipsPanel = tipsPanel;
			return true;
		}.bind( this ) );
	};

	/**
	 * Add or remove the footer; this is called when toggling edit mode.
	 *
	 * @param {boolean} inEditMode
	 */
	SuggestedEditsPanel.prototype.toggleFooter = function ( inEditMode ) {
		if ( !this.footerPanel ) {
			return;
		}
		if ( inEditMode ) {
			this.footerPanel.toggle( false );
			this.$element.removeClass( 'suggested-edits-panel-with-footer' );
		} else {
			this.footerPanel.toggle( true );
			this.$element.addClass( 'suggested-edits-panel-with-footer' );
		}
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
		return $( '<div>' ).addClass( 'suggested-edits-panel-footer-text' )
			// The following messages are used here:
			// * growthexperiments-help-panel-suggestededits-footer-mobile
			// * growthexperiments-help-panel-suggestededits-footer-desktop
			.html( mw.message( 'growthexperiments-help-panel-suggestededits-footer-' +
				( OO.ui.isMobile() ? 'mobile' : 'desktop' ) ).parse() );
	};

	module.exports = SuggestedEditsPanel;

}() );
