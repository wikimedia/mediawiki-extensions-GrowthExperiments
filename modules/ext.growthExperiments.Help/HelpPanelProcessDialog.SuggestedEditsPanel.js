( function () {
	'use strict';

	const suggestedEditsPeek = require( '../ui-components/SuggestedEditsPeek.js' ),
		quickStartTips = require( './QuickStartTips.js' ),
		SwitchEditorPanel = require( './HelpPanelProcessDialog.SwitchEditorPanel.js' );

	/**
	 * Create the suggested edit panel. The panel is initially empty; the code creating it
	 * must call SuggestedEditsPanel.build() to populate it.
	 *
	 * @param {Object} config The standard config to pass to OO.ui.PanelLayout,
	 *  plus configuration specific to building the suggested edits panel.
	 * @param {Object} config.taskTypeData The data for a particular task.
	 * @param {string} config.editorInterface The editor interface in use
	 * @param {string} config.preferredEditor The preferred editor interface for
	 * suggested edits.
	 * @param {string} config.currentTip The tip to preselect in the quick tips section.
	 * @param {jQuery} [config.parentWindow] OOUI window containing the panel.
	 * @constructor
	 */
	function SuggestedEditsPanel( config ) {
		SuggestedEditsPanel.super.call( this, Object.assign( {
			expanded: false,
			scrollable: false,
			continuous: true
		}, config ) );
		this.config = config;
		if ( !config.taskTypeData ) {
			return;
		}
		this.editorInterface = config.editorInterface;
		this.preferredEditor = config.preferredEditor;
		this.taskTypeData = config.taskTypeData;
		this.currentTip = config.currentTip;
		this.$scrollHeader = config.parentWindow.$head;
		/** @member {OO.ui.StackLayout} */
		this.tipsPanel = null;
	}

	OO.inheritClass( SuggestedEditsPanel, OO.ui.StackLayout );

	/**
	 * Appends the header, quickstart tips, and footer to the panel.
	 *
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
		this.switchEditorPanel = new SwitchEditorPanel( {
			editor: this.editorInterface,
			preferredEditor: this.preferredEditor,
			padded: true
		} );
		this.headerAndTipsPanel = new OO.ui.StackLayout( {
			padded: false,
			expanded: false,
			continuous: true,
			scrollable: true,
			classes: [ 'suggested-edits-panel-headerAndTips' ]
		} );
		this.headerAndTipsPanel.$element.on( 'scroll', this.setScrolledClasses.bind( this ) );
		this.headerPanel = new OO.ui.PanelLayout( {
			padded: false,
			expanded: false,
			$content: this.getHeader()
		} );
		return quickStartTips.getTips(
			this.taskTypeData.id, this.editorInterface, this.currentTip
		).then( ( tipsPanel ) => {
			this.headerAndTipsPanel.addItems( [ this.headerPanel, this.switchEditorPanel,
				tipsPanel ] );
			this.addItems( [ this.headerAndTipsPanel, this.footerPanel ] );
			// Used by the auto-advance logic in HelpPanelProcessDialog
			this.tipsPanel = tipsPanel;
			tipsPanel.tabIndexLayout.on( 'set', this.setScrolledClasses.bind( this ) );
			this.setScrolledClasses();
			return true;
		} );
	};

	/**
	 * Add or remove the footer; this is called when toggling edit mode.
	 *
	 * @param {boolean} inEditMode
	 */
	SuggestedEditsPanel.prototype.toggleFooter = function ( inEditMode ) {
		if ( this.footerPanel ) {
			this.footerPanel.toggle( !inEditMode );
			this.$element.toggleClass( 'suggested-edits-panel-with-footer', !inEditMode );
		}
	};

	/**
	 * Show/hide the Switch Editor panel, called when toggling edit mode.
	 *
	 * @param {boolean} inEditMode
	 * @param {string} currentEditor
	 */
	SuggestedEditsPanel.prototype.toggleSwitchEditorPanel = function ( inEditMode, currentEditor ) {
		if ( this.switchEditorPanel ) {
			this.switchEditorPanel.toggle( inEditMode, currentEditor );
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
			this.taskTypeData.difficulty,
			this.taskTypeData.iconData
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

	/**
	 * Add an 'obscures-tips' class to the header and footer above / below the panel when the
	 * tips are scrolled underneath them (ie, for the header, when there is a scrollbar on the
	 * panel and it is not in the topmost position; for the footer, the same with the bottommost
	 * position).
	 */
	SuggestedEditsPanel.prototype.setScrolledClasses = function () {
		const panel = this.headerAndTipsPanel.$element.get( 0 ),
			header = this.$scrollHeader.get( 0 ),
			footer = this.footerPanel.$element.get( 0 ),
			topObscured = ( panel.scrollTop !== 0 ),
			bottomObscured = ( panel.scrollTop + panel.clientHeight !== panel.scrollHeight );

		if ( header && topObscured !== header.classList.contains( 'obscures-tips' ) ) {
			header.classList.toggle( 'obscures-tips' );
		}
		if ( footer && bottomObscured !== footer.classList.contains( 'obscures-tips' ) ) {
			footer.classList.toggle( 'obscures-tips' );
		}
	};

	module.exports = SuggestedEditsPanel;

}() );
