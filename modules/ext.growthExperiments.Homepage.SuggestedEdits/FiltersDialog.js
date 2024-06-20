'use strict';

var ArticleCountWidget = require( './ArticleCountWidget.js' );

/**
 * Dialog for filtering tasks in the suggested edits feed
 *
 * Emits the following OOJS events:
 * - search: when the filter selection changes.
 * - done: when the dialog is closed (saved). First argument is the promise to be resolved when
 *   the tasks with the applied filters have been fetched.
 * On canceling the dialog, it will emit a search event with the original (pre-opening) filter list
 * if it differs from the filter list at closing, and the filter list at closing is not empty.
 *
 * Expects updateMatchCount() to be called back with the number of matches after emitting
 * a search event.
 *
 * @class mw.libs.ge.FiltersDialog
 * @extends OO.ui.ProcessDialog
 * @param {Object} config
 * @param {Array} config.presets List of enabled task types. Will be updated on close.
 */
function FiltersDialog( config ) {
	FiltersDialog.super.call( this, config );
}

OO.inheritClass( FiltersDialog, OO.ui.ProcessDialog );

/** @inheritDoc **/
FiltersDialog.prototype.initialize = function () {
	FiltersDialog.super.prototype.initialize.call( this );
	this.$element.addClass( 'mw-ge-filtersDialog skin-invert notheme' );
	this.$foot.addClass( 'mw-ge-filtersDialog-footer' );
	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.footerPanelLayout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.articleCounter = new ArticleCountWidget();
	this.footerPanelLayout.$element.append( this.articleCounter.$element );
};

/**
 * Save the selected filters
 *
 * @abstract
 */
FiltersDialog.prototype.savePreferences = null;

/**
 * Get an array of selected filters
 *
 * @abstract
 */
FiltersDialog.prototype.getEnabledFilters = null;

/**
 * @abstract
 */
FiltersDialog.prototype.updateFiltersFromState = null;

/**
 * Update the number of articles found with the selected filters
 *
 * @param {number} count
 */
FiltersDialog.prototype.updateMatchCount = function ( count ) {
	this.articleCounter.setCount( count );
};

/**
 * Update the state of the dialog header to "in progress" while the
 * NewcomerTaskStore is loading.
 *
 * @param {Object} state The relevant state properties
 * @param {boolean} state.isLoading Whereas the NewcomerTaskStore is fetching results
 * @param {number} state.count The number of tasks in the store queue
 */
FiltersDialog.prototype.updateLoadingState = function ( state ) {
	var actions = this.actions.get();
	var primaryAction = actions.length && actions[ 0 ];
	if ( state.isLoading ) {
		this.pushPending();
		if ( primaryAction ) {
			primaryAction.setDisabled( true );
		}
	} else {
		this.popPending();
		if ( primaryAction ) {
			primaryAction.setDisabled( state.count === 0 );
		}
	}
};

/** @inheritDoc **/
FiltersDialog.prototype.getActionProcess = function ( action ) {
	return FiltersDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				// Show the loading state of the ProcessDialog while tasks are fetched
				var promise = $.Deferred();
				this.savePreferences();
				this.emit( 'done', promise );
				promise.always( function () {
					this.close( { action: 'done' } );
				}.bind( this ) );
				return promise;
			}
			if ( action === 'cancel' || !action ) {
				this.emit( 'cancel' );
				this.close( { action: 'cancel' } );
			}
		}, this );
};

module.exports = FiltersDialog;
