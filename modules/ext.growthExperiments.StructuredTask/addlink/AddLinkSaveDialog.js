const LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' ),
	StructuredTaskSaveDialog = require( '../StructuredTaskSaveDialog.js' );

/**
 * Mixin for code sharing between AddLinkDesktopSaveDialog and AddLinkMobileSaveDialog.
 * This is to solve the diamond inheritance problem of ve.ui.MWSaveDialog -->
 * AddLinkDesktopSaveDialog and ve.ui.MWSaveDialog --> ve.ui.MWMobileSaveDialog.
 *
 * @mixin mw.libs.ge.ui.AddLinkSaveDialog
 * @extends ve.ui.MWSaveDialog
 * @mixes mw.libs.ge.ui.StructuredTaskSaveDialog
 *
 * @constructor
 */
function AddLinkSaveDialog() {
	StructuredTaskSaveDialog.call( this );
	this.$element.addClass( 'ge-addlink-mwSaveDialog' );
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'editsummary_dialog'
		/* eslint-enable camelcase */
	} );
}

OO.initClass( AddLinkSaveDialog );
OO.mixinClass( AddLinkSaveDialog, StructuredTaskSaveDialog );

/**
 * Return header for column showing whether the suggestion was linked
 *
 * @override
 *
 * @return {jQuery}
 */
AddLinkSaveDialog.prototype.getSuggestionStateHeader = function () {
	return $( '<th>' ).text(
		mw.message( 'growthexperiments-addlink-summary-column-header-linked' ).text()
	);
};

/**
 * Return table header elements for summary table
 *
 * @return {jQuery[]} Table header elements
 */
AddLinkSaveDialog.prototype.getSummaryTableHeader = function () {
	const $suggestionCol = $( '<th>' ).append(
		new OO.ui.IconWidget( { icon: 'robot-black' } ).$element,
		$( '<span>' ).addClass( 'aligner' ).text(
			mw.message( 'growthexperiments-addlink-summary-column-header-suggestion' ).text()
		)
	);
	return [ $suggestionCol, this.getSuggestionStateHeader() ];
};

/** @inheritDoc */
AddLinkSaveDialog.prototype.initialize = function () {
	StructuredTaskSaveDialog.prototype.initialize.call( this );

	// Replace the save panel. The other panels are good as they are.
	this.savePanel.$element.empty();
	this.$summaryTableBody = $( '<tbody>' );
	// Table content is set on dialog open as it needs to be dynamic.
	this.$summaryTable = $( '<table>' ).addClass( 'ge-addlink-mwSaveDialog-summaryTable' );
	this.$summaryTable.append(
		$( '<caption>' ).text(
			mw.message( 'growthexperiments-addlink-summary-title' ).text()
		),
		$( '<thead>' ).append(
			$( '<tr>' ).append( this.getSummaryTableHeader() )
		),
		this.$summaryTableBody
	);

	this.$copyrightFooter = $( '<p>' ).addClass( 'ge-structuredTask-copyrightwarning' ).append(
		mw.message( 'growthexperiments-addlink-summary-copyrightwarning' ).parse()
	);
	this.$copyrightFooter.find( 'a' ).attr( 'target', '_blank' );
	this.$watchlistFooter = $( '<div>' );

	this.savePanel.$element.append(
		this.$summaryTable,
		this.$watchlistFooter,
		this.$copyrightFooter
	);
};

/**
 * Render summary table body based on the specified suggestion states
 *
 * @param {Object[]} annotationStates Suggestion states (acceptances/rejections/skips)
 */
AddLinkSaveDialog.prototype.updateSummary = function ( annotationStates ) {
	const $rows = annotationStates.map( ( state ) => {
		let $icon;
		if ( state.accepted ) {
			$icon = new OO.ui.IconWidget( { icon: 'check', flags: 'progressive' } ).$element;
		} else if ( state.rejected ) {
			$icon = new OO.ui.IconWidget( { icon: 'close-destructive' } ).$element;
		} else {
			$icon = new OO.ui.IconWidget( { icon: 'subtract' } ).$element;
		}
		return $( '<tr>' ).append(
			$( '<td>' ).append(
				mw.html.escape( state.text ),
				mw.message( 'colon-separator' ).parse(),
				new OO.ui.IconWidget( { icon: 'link' } ).$element,
				mw.html.escape( state.title )
			).addClass( 'ge-addlink-mwSaveDialog-table-data' ),
			$( '<td>' ).append(
				$icon
			).addClass( 'ge-addlink-mwSaveDialog-table-data' )
		);
	} );
	this.$summaryTableBody.empty().append.apply( this.$summaryTableBody, $rows );
};

/** @inheritDoc */
AddLinkSaveDialog.prototype.getSetupProcess = function ( data ) {
	return StructuredTaskSaveDialog.prototype.getSetupProcess.call( this, data ).next( function () {
		let acceptedCount, rejectedCount, skippedCount;
		const annotationStates = ve.init.target.getAnnotationStates();
		acceptedCount = rejectedCount = skippedCount = 0;
		annotationStates.forEach( ( state ) => {
			// convert to  boolean to avoid NaNs
			acceptedCount += !!state.accepted;
			rejectedCount += !!state.rejected;
			skippedCount += !!state.skipped;
		} );

		// Change button label to "Done" if at least one suggestion is rejected and none are accepted
		if ( rejectedCount > 0 && acceptedCount === 0 ) {
			const saveButton = this.actions.get( { actions: 'save' } ).pop();
			if ( saveButton ) {
				saveButton.setLabel( mw.message( 'growthexperiments-addlink-done-button' ).text() );
			}
		}

		this.updateSummary( annotationStates );
		// Edit summary will be localized in the content language via FormatAutocomments hook
		this.editSummaryInput.setValue(
			'/* growthexperiments-addlink-summary-summary:' +
			acceptedCount + '|' + rejectedCount + '|' + skippedCount + ' */'
		);
		this.$watchlistFooter.empty();
		if ( acceptedCount ) {
			this.$watchlistFooter.append( this.getWatchlistCheckbox() );
		}
		this.logger.log( 'impression', {
			/* eslint-disable camelcase */
			accepted_count: acceptedCount,
			rejected_count: rejectedCount,
			skipped_count: skippedCount
			/* eslint-enable camelcase */
		} );
	}, this );
};

module.exports = AddLinkSaveDialog;
