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

MachineSuggestionsPlaceholderTool.prototype.updateTitleText = function ( titleText ) {
	if ( !this.$titleText ) {
		this.$titleText = this.$element.find( '.mw-ge-machine-suggestions-mode-title-text' );
		this.originalTitleText = this.$titleText.text();
	}
	this.$titleText.text( titleText );
};

MachineSuggestionsPlaceholderTool.prototype.restoreOriginalTitleText = function () {
	if ( this.$titleText && this.originalTitleText ) {
		this.$titleText.text( this.originalTitleText );
	}
};

module.exports = MachineSuggestionsPlaceholderTool;
