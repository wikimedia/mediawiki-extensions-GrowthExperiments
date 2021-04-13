/**
 * No-op tool serving as placeholder
 *
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

module.exports = MachineSuggestionsPlaceholderTool;
