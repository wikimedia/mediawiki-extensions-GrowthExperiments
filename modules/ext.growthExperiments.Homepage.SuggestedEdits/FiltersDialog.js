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
	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.footerPanelLayout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.articleCounter = new ArticleCountWidget();
};

/**
 * Save the selected filters
 *
 * @abstract
 */
FiltersDialog.prototype.savePreferences = function () {
	throw new Error( 'savePreferences must be implemented by the subclass' );
};

/**
 * Get an array of selected filters
 *
 * @abstract
 */
FiltersDialog.prototype.getEnabledFilters = function () {
	throw new Error( 'getEnabledFilters must be implemented by the subclass' );
};

/**
 * @abstract
 */
FiltersDialog.prototype.updateFiltersFromState = function () {
	throw new Error( 'updateFiltersFromState must be implemented by the subclass' );
};

/**
 * Update the number of articles found with the selected filters
 *
 * @param {number} count
 */
FiltersDialog.prototype.updateMatchCount = function ( count ) {
	this.articleCounter.setCount( count );
	this.footerPanelLayout.toggle( true );
};

/** @inheritDoc **/
FiltersDialog.prototype.getActionProcess = function ( action ) {
	return FiltersDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'close' ) {
				// Show the loading state of the ProcessDialog while tasks are fetched
				var promise = $.Deferred();
				this.savePreferences();
				this.config.presets = this.getEnabledFilters();
				this.emit( 'done', promise );
				promise.always( function () {
					this.close( { action: 'done' } );
				}.bind( this ) );
				return promise;
			}
			if ( action === 'cancel' ) {
				this.updateFiltersFromState();
				this.emit( 'cancel' );
				this.close( { action: 'cancel' } );
			}
		}, this );
};

module.exports = FiltersDialog;
