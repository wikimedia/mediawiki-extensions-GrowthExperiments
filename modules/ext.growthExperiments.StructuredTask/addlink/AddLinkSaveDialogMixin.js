var LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' );

/**
 * Mixin for code sharing between AddLinkSaveDialog and AddLinkMobileSaveDialog.
 * This is to solve the diamond inheritance problem of ve.ui.MWSaveDialog -->
 * AddLinkSaveDialog and ve.ui.MWSaveDialog --> ve.ui.MWMobileSaveDialog.
 *
 * @mixin mw.libs.ge.ui.AddLinkSaveDialogMixin
 * @extends ve.ui.MWSaveDialog
 *
 * @constructor
 */
function AddLinkSaveDialogMixin() {
	this.$element.addClass( 'ge-addlink-mwSaveDialog' );
	this.logger = new LinkSuggestionInteractionLogger( {
		/* eslint-disable camelcase */
		is_mobile: OO.ui.isMobile(),
		active_interface: 'editsummary_dialog'
		/* eslint-enable camelcase */
	} );
}

OO.initClass( AddLinkSaveDialogMixin );

/**
 * Return header for column showing whether the suggestion was linked
 *
 * @override
 *
 * @return {jQuery}
 */
AddLinkSaveDialogMixin.prototype.getSuggestionStateHeader = function () {
	return $( '<th>' ).append(
		mw.message( 'growthexperiments-addlink-summary-column-header-linked' ).text()
	);
};

/**
 * Render summary table body based on the specified suggestion states
 *
 * @param {Object[]} annotationStates Suggestion states (acceptances/rejections/skips)
 */
AddLinkSaveDialogMixin.prototype.updateSummary = function ( annotationStates ) {
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
AddLinkSaveDialogMixin.prototype.getSetupProcess = function ( data ) {
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

module.exports = AddLinkSaveDialogMixin;
