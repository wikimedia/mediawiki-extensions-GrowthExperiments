/**
 * No-op tool serving as placeholder for custom title in the toolbar
 *
 * @class mw.libs.ge.MachineSuggestionsPlaceholder
 * @extends ve.ui.Tool
 *
 * @constructor
 */
function MachineSuggestionsPlaceholderTool() {
	MachineSuggestionsPlaceholderTool.super.apply( this, arguments );
}

OO.inheritClass( MachineSuggestionsPlaceholderTool, ve.ui.Tool );

MachineSuggestionsPlaceholderTool.static.name = 'machineSuggestionsPlaceholder';
MachineSuggestionsPlaceholderTool.static.title = '';

/**
 * Update the toolbar title
 *
 * @param {string} titleText New title text
 * @param {boolean} [isLoading] Whether the loading state should be shown
 */
MachineSuggestionsPlaceholderTool.prototype.updateTitleText = function (
	titleText,
	isLoading,
) {
	if ( !this.$titleText ) {
		this.$titleText = this.$element.find( '.mw-ge-machine-suggestions-mode-title-text' );
		this.originalTitleText = this.$titleText.text();
	}
	this.$titleText.text( titleText ).toggleClass(
		'mw-ge-machine-suggestions-mode-title-text--is-loading',
		!!isLoading,
	);
};

/**
 * Restore the original title state
 */
MachineSuggestionsPlaceholderTool.prototype.restoreOriginalTitleText = function () {
	if ( this.$titleText && this.originalTitleText ) {
		this.$titleText.text( this.originalTitleText ).removeClass(
			'mw-ge-machine-suggestions-mode-title-text--is-loading',
		);
	}
};

module.exports = MachineSuggestionsPlaceholderTool;
