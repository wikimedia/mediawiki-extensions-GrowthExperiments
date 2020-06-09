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
		this.build();
	}

	OO.inheritClass( SuggestedEditsPanel, OO.ui.StackLayout );

	/**
	 * Appends the header, quickstart tips, and footer to the panel.
	 */
	SuggestedEditsPanel.prototype.build = function () {
		this.$element.addClass( 'suggested-edits-panel' );
		quickStartTips.getTips( this.taskTypeData.id, this.editorInterface ).then( function ( tipsPanel ) {
			var headerPanel = new OO.ui.PanelLayout( {
					padded: false,
					expanded: false,
					$content: this.getHeader()
				} ),
				headerAndTipsPanel = new OO.ui.StackLayout( {
					padded: false,
					expanded: false,
					continuous: true,
					scrollable: true,
					classes: [ 'suggested-edits-panel-headerAndTips' ]
				} );
			this.footerPanel = new OO.ui.PanelLayout( {
				// Padding is set on the text itself in CSS
				padded: false,
				expanded: false,
				classes: [ 'suggested-edits-panel-footer' ],
				$content: this.getFooter()
			} );

			headerAndTipsPanel.addItems( [ headerPanel, tipsPanel ] );
			this.addItems( [ headerAndTipsPanel, this.footerPanel ] );

			tipsPanel.on( 'tab-selected', function ( data ) {
				this.emit( 'tab-selected', data );
			}, [], this );
		}.bind( this ) );
	};

	SuggestedEditsPanel.prototype.hideFooter = function () {
		this.removeItems( [ this.footerPanel ] );
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
