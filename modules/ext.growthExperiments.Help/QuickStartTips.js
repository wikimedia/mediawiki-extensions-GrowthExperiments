( function () {
	'use strict';

	const QuickStartTipsTabPanelLayout = require( './QuickStartTipsTabPanelLayout.js' );

	/**
	 * Build a minimal tips panel when quick-start tips are skipped (e.g., Revise Tone
	 * uses onboarding instead of server-provided tips).
	 *
	 * The Help Panel expects a StackLayout with a tabIndexLayout; this helper
	 * supplies a compatible structure with custom content to avoid runtime errors.
	 *
	 * @param {jQuery|null} content Element to render inside the panel.
	 * @return {OO.ui.StackLayout}
	 */
	function createFallbackTipsPanel( content ) {
		const stackLayout = new OO.ui.StackLayout( {
				classes: [ 'suggested-edits-panel-quick-start-tips-content' ],
				continuous: true,
				scrollable: true,
				expanded: false,
			} ),
			panel = new OO.ui.PanelLayout( {
				padded: false,
				expanded: false,
				$content: content || $( '<div>' ),
			} );
		stackLayout.addItems( [ panel ] );
		// Provide a minimal tabIndexLayout to avoid downstream errors
		stackLayout.tabIndexLayout = {
			on: function () {},
			getTabs: function () {
				return new OO.ui.TabSelectWidget( { framed: false } );
			},
		};
		return stackLayout;
	}

	/**
	 * @param {string} taskTypeID The task type ID
	 * @param {string} editorInterface The editor interface
	 * @param {string|null} currentTabPanel The current tab panel to select, if any.
	 * @return {jQuery.Promise} Promise that resolves with an OO.ui.StackLayout
	 */
	function getTips( taskTypeID, editorInterface, currentTabPanel ) {
		if ( taskTypeID === 'revise-tone' ) {
			// Revise Tone has no quick-start tips. The panel is built but never shown
			// because clicking the revise-tone tile launches the quiz directly (early return
			// in HelpPanelProcessDialog.js). We just need to return a valid panel structure.
			return $.Deferred().resolve( createFallbackTipsPanel( null ) ).promise();
		}

		const indexLayout = new OO.ui.IndexLayout( {
				framed: false,
				expanded: false,
				classes: [ 'suggested-edits-panel-quick-start-tips-pager' ],
			} ),
			stackLayout = new OO.ui.StackLayout( {
				classes: [ 'suggested-edits-panel-quick-start-tips-content' ],
				continuous: true,
				scrollable: true,
				expanded: false,
			} ),
			tipPanels = [],
			contentPanel = new OO.ui.PanelLayout( {
				padded: false,
				expanded: false,
			} ),
			// Assume VE if in reading mode, since clicking Edit won't trigger
			// a page reload, and we currently don't vary messages by reading
			// interface
			apiPath = [
				mw.config.get( 'wgScriptPath' ),
				'rest.php',
				'growthexperiments',
				'v0',
				'quickstarttips',
				mw.config.get( 'skin' ),
				editorInterface,
				taskTypeID,
				mw.config.get( 'wgUserLanguage' ),
			].join( '/' );
		let tipPanel,
			tipLabelNumber = 1;

		return $.get( apiPath ).then( ( quickStartTipsData ) => {
			for ( const key in quickStartTipsData ) {
				tipPanel = new QuickStartTipsTabPanelLayout( 'tipset-' + String( tipLabelNumber ), {
					taskType: taskTypeID,
					label: String( mw.language.convertNumber( tipLabelNumber ) ),
					data: quickStartTipsData[ key ],
				} );
				tipPanels.push( tipPanel );
				tipLabelNumber++;
			}
			indexLayout.addTabPanels( tipPanels );
			if ( currentTabPanel ) {
				indexLayout.setTabPanel( currentTabPanel );
			}
			contentPanel.$element.append( indexLayout.$element );
			stackLayout.addItems( [
				new OO.ui.PanelLayout( {
					padded: false,
					expanded: false,
					$content: $( '<h4>' ).addClass( 'suggested-edits-panel-quick-start-tips' )
						.text( mw.message( 'growthexperiments-help-panel-suggestededits-quick-start-tips' ).text() ),
				} ),
				contentPanel,
			] );
			// Used by the auto-advance logic in HelpPanelProcessDialog
			stackLayout.tabIndexLayout = indexLayout;
			return stackLayout;
		}, ( jqXHR, statusText, error ) => {
			mw.log.error( 'Unable to load quick start tips', statusText, error );
			mw.errorLogger.logError( new Error( 'Unable to load quick start tips: ' +
				statusText + ' / ' + error ), 'error.growthexperiments' );
			return createFallbackTipsPanel( null );
		} );
	}

	module.exports = {
		getTips: getTips,
	};
}() );
