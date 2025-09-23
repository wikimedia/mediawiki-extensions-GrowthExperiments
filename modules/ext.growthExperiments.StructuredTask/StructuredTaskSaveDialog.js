const SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ),
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
	this.alreadySetupUserErrorContent = false;
}

OO.initClass( StructuredTaskSaveDialog );

/**
 * @inheritDoc
 * @description Classes using the mixin should call this method instead of their parent method.
 */
StructuredTaskSaveDialog.prototype.initialize = function () {
	this.constructor.super.prototype.initialize.call( this );

	// Snapshot the homepage PV token. It will change during save, and we want the events
	// belonging to this dialog to be grouped together.
	this.homepagePageviewToken = SuggestedEditSession.getInstance().clickId;
};

/**
 * @inheritDoc
 * @description Classes using the mixin should call this method instead of their parent method.
 */
StructuredTaskSaveDialog.prototype.getSetupProcess = function ( data ) {
	return this.constructor.super.prototype.getSetupProcess.call( this,
		this.getDialogData( data ),
	).first( function () {
		// Hide the preview and diff views if the user did not accept anything, and so submitting
		// will cause no change to the article.
		if ( !ve.init.target.hasEdits() ) {
			data.canPreview = data.canReview = false;
			data.saveButtonLabel = mw.message(
				'growthexperiments-structuredtask-summary-submit',
			).text();
		}
		this.setVisualDiffPreference();
	}, this );
};

/** @inheritDoc */
StructuredTaskSaveDialog.prototype.getTeardownProcess = function ( data ) {
	return this.constructor.super.prototype.getTeardownProcess.call(
		this, data,
	).next( function () {
		const suggestedEditSession = SuggestedEditSession.getInstance();

		// T283765: use the stored pageview token. The real one might have been reset at
		// this point by a showPostEditDialog call from the postEdit hook.
		SuggestionInteractionLogger.log( 'close', this.getLogMetadata(), {
			/* eslint-disable camelcase */
			homepage_pageview_token: this.homepagePageviewToken,
			active_interface: 'editsummary_dialog',
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
	return this.constructor.super.prototype.getActionProcess.call(
		this, action,
	).next( () => {
		// The following actions are used here:
		// * editsummary_save
		// * editsummary_review
		// * editsummary_approve
		// * editsummary_report
		if ( [ 'save', 'review', 'approve', 'report' ].includes( action ) ) {
			SuggestionInteractionLogger.log( 'editsummary_' + action, this.getLogMetadata(), {
				/* eslint-disable camelcase */
				homepage_pageview_token: this.homepagePageviewToken,
				active_interface: 'editsummary_dialog',
				/* eslint-enable camelcase */
			} );
		}
		// On cancel, return focus to the inspector
		if ( action === '' ) {
			this.manager.lifecycle.closed.then( () => {
				mw.hook( 'inspector-regainfocus' ).fire();
			} );
		}
	} );
};

/**
 * Set a fake user preference for visual diffs to avoid T281924.
 */
StructuredTaskSaveDialog.prototype.setVisualDiffPreference = function () {
	// The extra quote is needed because VE uses JSON preferences.
	mw.user.options.set( 'visualeditor-diffmode-machineSuggestions', '"visual"' );
};

/**
 * Set up error message and button for when the user is logged out during editing.
 *
 * @param {string|null} username Name of newly logged-in user, or null if anonymous
 */
StructuredTaskSaveDialog.prototype.setupUserErrorContent = function ( username ) {
	const errorMessage = new OO.ui.MessageWidget( {
			type: 'error',
			label: mw.message( 'growthexperiments-structuredtask-user-error' )
				.params( [ username ] ).escaped(),
		} ),
		loginButton = new OO.ui.ButtonWidget( {
			label: mw.message(
				'growthexperiments-structuredtask-user-error-login-cta',
			).escaped(),
			flags: [ 'primary', 'progressive' ],
		} );
	loginButton.connect( this, { click: 'onLoginButtonClicked' } );
	this.$element.find( '.oo-ui-processDialog-errors-title' ).after( errorMessage.$element );
	this.$element.find( '.oo-ui-processDialog-errors-actions' ).append( loginButton.$element );
	this.alreadySetupUserErrorContent = true;
};

/**
 * Show an error when the user is logged out during editing
 *
 * @param {string|null} username Name of newly logged-in user, or null if anonymous
 */
StructuredTaskSaveDialog.prototype.showUserError = function ( username ) {
	if ( !this.alreadySetupUserErrorContent ) {
		this.setupUserErrorContent( username );
	}
	this.$element.find( '.oo-ui-processDialog-errors' ).removeClass( 'oo-ui-element-hidden' );
	if ( this.retryButton ) {
		this.retryButton.toggle( false );
	}
};

/**
 * Go to Special:UserLogin
 */
StructuredTaskSaveDialog.prototype.onLoginButtonClicked = function () {
	// Suppress default unsaved changes warning since it's in the error message
	$( window ).off( 'beforeunload' );
	window.onbeforeunload = null;
	location.href = new mw.Title( 'Special:UserLogin' ).getUrl( {
		returnto: 'Special:Homepage',
	} );
};

/**
 * Get the field data for watchlist checkbox and watchlist expiry fields.
 *
 * This dialog data is used to construct the minor edit and watchlist checkboxes. In this case,
 * only the watchlist checkbox should be shown.
 *
 * @param {Object} data
 * @return {Object}
 */
StructuredTaskSaveDialog.prototype.getDialogData = function ( data ) {
	let checkboxFields = data.checkboxFields || [];
	const checkboxesByName = data.checkboxesByName || {},
		allowList = [ checkboxesByName.wpWatchthis, checkboxesByName.wpWatchlistExpiry ];

	checkboxFields = checkboxFields.filter( ( fieldLayout ) => allowList.includes( fieldLayout.getField() ) );
	return Object.assign( {}, data, {
		checkboxFields: checkboxFields,
	} );
};

/**
 * Get the watchlist field (including the label and the watchlist expiry (when $wgWatchlistExpiry
 * is true).
 *
 * @return {jQuery|undefined}
 */
StructuredTaskSaveDialog.prototype.getWatchlistCheckbox = function () {
	if ( !this.checkboxesByName.wpWatchthis ) {
		return;
	}
	this.$saveCheckboxes.addClass( 'ge-structuredTask-mwSaveDialog-watchlistCheckbox' );
	return this.$saveCheckboxes;
};

/**
 * Get action data for StructuredTaskLogger
 *
 * @return {Object}
 */
StructuredTaskSaveDialog.prototype.getLogMetadata = function () {
	return {};
};

module.exports = StructuredTaskSaveDialog;
