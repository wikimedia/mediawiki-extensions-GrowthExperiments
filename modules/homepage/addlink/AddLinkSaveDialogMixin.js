var LinkSuggestionInteractionLogger = require( './LinkSuggestionInteractionLogger.js' ),
	SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' );

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

/** @inheritDoc */
AddLinkSaveDialogMixin.prototype.initialize = function () {
	this.constructor.super.prototype.initialize.call( this );

	// Snapshot the homepage PV token. It will change during save, and we want the events
	// belonging to this dialog to be grouped together.
	this.homepagePageviewToken = SuggestedEditSession.getInstance().clickId;

	// Replace the save panel. The other panels are good as they are.
	this.savePanel.$element.empty();
	this.$summaryTableBody = $( '<tbody>' );
	// Table content is set on dialog open as it needs to be dynamic.
	this.$summaryTable = $( '<table>' ).addClass( 'ge-addlink-summaryTable' );
	this.$summaryTable.append(
		$( '<caption>' ).append(
			mw.message( 'growthexperiments-addlink-summary-title' ).text()
		),
		$( '<thead>' ).append(
			$( '<tr>' ).append(
				$( '<th>' ).append(
					new OO.ui.IconWidget( { icon: 'robot-black' } ).$element,
					$( '<span>' ).addClass( 'aligner' ).append(
						mw.message( 'growthexperiments-addlink-summary-column-header-suggestion' ).text()
					)
				),
				$( '<th>' ).append(
					mw.message( 'growthexperiments-addlink-summary-column-header-linked' ).text()
				)
			)
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

AddLinkSaveDialogMixin.prototype.updateSummary = function ( annotationStates ) {
	var $rows = annotationStates.map( function ( state ) {
		var $icon;
		if ( state.accepted ) {
			$icon = new OO.ui.IconWidget( { icon: 'check', flags: 'progressive' } ).$element;
		} else if ( state.rejected ) {
			$icon = new OO.ui.IconWidget( { icon: 'close', flags: 'destructive' } ).$element;
		} else {
			$icon = new OO.ui.IconWidget( { icon: 'subtract' } ).$element;
		}
		return $( '<tr>' ).append(
			$( '<td>' ).append(
				mw.html.escape( state.text ),
				mw.message( 'colon-separator' ).parse(),
				new OO.ui.IconWidget( { icon: 'link' } ).$element,
				mw.html.escape( state.title )
			),
			$( '<td>' ).append(
				$icon
			)
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
	return this.constructor.super.prototype.getSetupProcess.call( this, data ).first( function () {
		// Set a fake user preference for visual diffs to avoid T281924.
		// The extra quote is needed because VE uses JSON preferences.
		mw.user.options.set( 'visualeditor-diffmode-machineSuggestions', '"visual"' );
	} ).next( function () {
		var acceptedCount, rejectedCount, skippedCount;
		acceptedCount = rejectedCount = skippedCount = 0;
		annotationStates.forEach( function ( state ) {
			// convert to  boolean to avoid NaNs
			acceptedCount += !!state.accepted;
			rejectedCount += !!state.rejected;
			skippedCount += !!state.skipped;
		} );

		this.updateSummary( annotationStates );
		this.editSummaryInput.setValue(
			mw.message( 'growthexperiments-addlink-summary-summary' ).params( [
				acceptedCount, rejectedCount, skippedCount
			] ).text()
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

/** @inheritDoc */
AddLinkSaveDialogMixin.prototype.getTeardownProcess = function ( data ) {
	return this.constructor.super.prototype.getTeardownProcess.call( this, data ).next( function () {
		var suggestedEditSession = SuggestedEditSession.getInstance();

		// T283765: use the stored pageview token. The real one might have been reset at
		// this point by a showPostEditDialog call from the postEdit hook.
		// eslint-disable-next-line camelcase
		this.logger.log( 'close', {}, { homepage_pageview_token: this.homepagePageviewToken } );

		// If the page was saved, try showing the post-edit dialog. This is a hack for the case
		// when no link recommendation was accepted so the save was a null edit and the postEdit
		// hook did not fire.
		if ( ve.init.target.madeNullEdit ) {
			suggestedEditSession.setTaskState( SuggestedEditSession.static.STATES.SUBMITTED );
			suggestedEditSession.showPostEditDialog( { resetSession: true } );
		}
	}, this );
};

/** @inheritDoc */
AddLinkSaveDialogMixin.prototype.getActionProcess = function ( action ) {
	return this.constructor.super.prototype.getActionProcess.call( this, action ).next( function () {
		if ( [ 'save', 'review', 'approve', 'report' ].indexOf( action ) >= 0 ) {
			this.logger.log( 'editsummary_' + action, {}, {
				// eslint-disable-next-line camelcase
				homepage_pageview_token: this.homepagePageviewToken
			} );
		}
		// On cancel, return focus to the link inspector.
		if ( action === '' ) {
			this.manager.lifecycle.closed.done( function () {
				mw.hook( 'addlink-regainfocus' ).fire();
			} );
		}
	}.bind( this ) );
};

module.exports = AddLinkSaveDialogMixin;
