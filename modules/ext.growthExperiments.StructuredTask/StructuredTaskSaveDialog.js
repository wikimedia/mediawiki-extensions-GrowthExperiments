var SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
	SuggestionInteractionLogger = require( './SuggestionInteractionLogger.js' );

/**
 * Mixin for setting up save dialog for structured tasks
 *
 * @mixin mw.libs.ge.ui.StructuredTaskSaveDialog
 * @extends ve.ui.MWSaveDialog
 *
 * @constructor
 */
function StructuredTaskSaveDialog() {
	this.$element.addClass( 'ge-structuredTask-mwSaveDialog' );
}

OO.initClass( StructuredTaskSaveDialog );

/**
 * Return table header element for suggestion state
 *
 * @return {jQuery}
 */
StructuredTaskSaveDialog.prototype.getSuggestionStateHeader = function () {
	return $( '<th>' );
};

/**
 * Return table header elements for summary table
 *
 * @return {jQuery[]} Table header elements
 */
StructuredTaskSaveDialog.prototype.getSummaryTableHeader = function () {
	var $suggestionCol = $( '<th>' ).append(
		new OO.ui.IconWidget( { icon: 'robot-black' } ).$element,
		$( '<span>' ).addClass( 'aligner' ).append(
			mw.message( 'growthexperiments-addlink-summary-column-header-suggestion' ).text()
		)
	);
	return [ $suggestionCol, this.getSuggestionStateHeader() ];
};

/** @inheritDoc */
StructuredTaskSaveDialog.prototype.initialize = function () {
	this.constructor.super.prototype.initialize.call( this );

	// Snapshot the homepage PV token. It will change during save, and we want the events
	// belonging to this dialog to be grouped together.
	this.homepagePageviewToken = SuggestedEditSession.getInstance().clickId;

	// Replace the save panel. The other panels are good as they are.
	this.savePanel.$element.empty();
	this.$summaryTableBody = $( '<tbody>' );
	// Table content is set on dialog open as it needs to be dynamic.
	this.$summaryTable = $( '<table>' ).addClass( 'ge-structuredTask-mwSaveDialog-summaryTable' );
	this.$summaryTable.append(
		$( '<caption>' ).append(
			mw.message( 'growthexperiments-addlink-summary-title' ).text()
		),
		$( '<thead>' ).append(
			$( '<tr>' ).append( this.getSummaryTableHeader() )
		),
		this.$summaryTableBody
	);
	this.$copyrightFooter = $( '<p>' ).addClass( 'ge-addlink-copyrightwarning' ).append(
		mw.message( 'growthexperiments-addlink-summary-copyrightwarning' ).parse()
	);
	this.$copyrightFooter.find( 'a' ).attr( 'target', '_blank' );
	this.savePanel.$element.append(
		this.$summaryTable,
		this.$copyrightFooter
	);
};

/** @inheritDoc */
StructuredTaskSaveDialog.prototype.getTeardownProcess = function ( data ) {
	return this.constructor.super.prototype.getTeardownProcess.call( this, data ).next( function () {
		var suggestedEditSession = SuggestedEditSession.getInstance();

		// T283765: use the stored pageview token. The real one might have been reset at
		// this point by a showPostEditDialog call from the postEdit hook.
		SuggestionInteractionLogger.log( 'close', {}, {
			/* eslint-disable camelcase */
			homepage_pageview_token: this.homepagePageviewToken,
			active_interface: 'editsummary_dialog'
			/* eslint-enable camelcase */
		} );

		// If the page was saved, try showing the post-edit dialog. This is a hack for the case
		// when no link recommendation was accepted so the save was a null edit and the postEdit
		// hook did not fire. This is only needed for desktop since postEditMobile hook is fired.
		if ( OO.ui.isMobile() ) {
			return;
		}

		if ( ve.init.target.madeNullEdit ) {
			suggestedEditSession.setTaskState( SuggestedEditSession.static.STATES.SUBMITTED );
			suggestedEditSession.showPostEditDialog( { resetSession: true } );
		}
	}, this );
};

/** @inheritDoc */
StructuredTaskSaveDialog.prototype.getActionProcess = function ( action ) {
	return this.constructor.super.prototype.getActionProcess.call( this, action ).next( function () {
		if ( [ 'save', 'review', 'approve', 'report' ].indexOf( action ) >= 0 ) {
			SuggestionInteractionLogger.log( 'editsummary_' + action, {}, {
				/* eslint-disable camelcase */
				homepage_pageview_token: this.homepagePageviewToken,
				active_interface: 'editsummary_dialog'
				/* eslint-enable camelcase */
			} );
		}
		// On cancel, return focus to the inspector
		if ( action === '' ) {
			this.manager.lifecycle.closed.done( function () {
				mw.hook( 'inspector-regainfocus' ).fire();
			} );
		}
	}.bind( this ) );
};

/**
 * Set a fake user preference for visual diffs to avoid T281924.
 */
StructuredTaskSaveDialog.prototype.setVisualDiffPreference = function () {
	// The extra quote is needed because VE uses JSON preferences.
	mw.user.options.set( 'visualeditor-diffmode-machineSuggestions', '"visual"' );
};

module.exports = StructuredTaskSaveDialog;
