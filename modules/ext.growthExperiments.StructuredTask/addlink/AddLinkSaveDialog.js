var LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' ),
	StructuredTaskSaveDialog = require( '../StructuredTaskSaveDialog.js' ),
	SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' );

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
	return $( '<th>' ).append(
		mw.message( 'growthexperiments-addlink-summary-column-header-linked' ).text()
	);
};

/**
 * Return table header elements for summary table
 *
 * @return {jQuery[]} Table header elements
 */
AddLinkSaveDialog.prototype.getSummaryTableHeader = function () {
	var $suggestionCol = $( '<th>' ).append(
		new OO.ui.IconWidget( { icon: 'robot-black' } ).$element,
		$( '<span>' ).addClass( 'aligner' ).append(
			mw.message( 'growthexperiments-addlink-summary-column-header-suggestion' ).text()
		)
	);
	return [ $suggestionCol, this.getSuggestionStateHeader() ];
};

/** @inheritDoc */
AddLinkSaveDialog.prototype.initialize = function () {
	this.constructor.super.prototype.initialize.call( this );

	// Snapshot the homepage PV token. It will change during save, and we want the events
	// belonging to this dialog to be grouped together.
	this.homepagePageviewToken = SuggestedEditSession.getInstance().clickId;

	// Replace the save panel. The other panels are good as they are.
	this.savePanel.$element.empty();
	this.$summaryTableBody = $( '<tbody>' );
	// Table content is set on dialog open as it needs to be dynamic.
	this.$summaryTable = $( '<table>' ).addClass( 'ge-addlink-mwSaveDialog-summaryTable' );
	this.$summaryTable.append(
		$( '<caption>' ).append(
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

	this.savePanel.$element.append(
		this.$summaryTable,
		this.$copyrightFooter
	);
};

/**
 * Render summary table body based on the specified suggestion states
 *
 * @param {Object[]} annotationStates Suggestion states (acceptances/rejections/skips)
 */
AddLinkSaveDialog.prototype.updateSummary = function ( annotationStates ) {
	var $rows = annotationStates.map( function ( state ) {
		var $icon;
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
	var annotationStates = ve.init.target.getAnnotationStates(),
		hasAccepts = annotationStates.some( function ( state ) {
			return state.accepted;
		} );
	if ( !hasAccepts ) {
		// Hide the preview and diff views if the user did not accept anything, and so submitting
		// will cause no change to the article.
		data.canPreview = data.canReview = false;
		data.saveButtonLabel = mw.message( 'growthexperiments-addlink-summary-submit' ).text();
	}
	return this.constructor.super.prototype.getSetupProcess.call( this, data ).first(
		this.setVisualDiffPreference.bind( this )
	).next( function () {
		var acceptedCount, rejectedCount, skippedCount;
		acceptedCount = rejectedCount = skippedCount = 0;
		annotationStates.forEach( function ( state ) {
			// convert to  boolean to avoid NaNs
			acceptedCount += !!state.accepted;
			rejectedCount += !!state.rejected;
			skippedCount += !!state.skipped;
		} );

		this.updateSummary( annotationStates );
		// Edit summary will be localized in the content language via FormatAutocomments hook
		this.editSummaryInput.setValue(
			'/* growthexperiments-addlink-summary-summary:' +
			acceptedCount + '|' + rejectedCount + '|' + skippedCount + ' */'
		);
		if ( this.checkboxesByName.wpWatchthis ) {
			this.checkboxesByName.wpWatchthis.setSelected( true );
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
