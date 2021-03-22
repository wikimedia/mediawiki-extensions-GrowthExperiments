/**
 * No-op tool serving as placeholder
 *
 * @extends ve.ui.Tool
 *
 * @constructor
 */
function AiSuggestionsPlaceholderTool() {
	AiSuggestionsPlaceholderTool.super.apply( this, arguments );
}
OO.inheritClass( AiSuggestionsPlaceholderTool, ve.ui.Tool );
AiSuggestionsPlaceholderTool.static.name = 'aiSuggestionsPlaceholder';
AiSuggestionsPlaceholderTool.static.title = '';

module.exports = AiSuggestionsPlaceholderTool;
