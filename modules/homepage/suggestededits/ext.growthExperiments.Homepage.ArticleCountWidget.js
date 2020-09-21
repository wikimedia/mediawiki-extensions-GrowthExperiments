/**
 * Widget that displays the number of articles matched by a set of filters, using an interface
 * message and an icon.
 *
 * The number must be set/updated using setCount().
 *
 * @class
 * @extends OO.ui.Widget
 * @constructor
 * @param {Object} config
 */
function ArticleCountWidget( config ) {
	config = config || {};
	// Parent constructor
	ArticleCountWidget.super.call( this, config );

	this.icon = new OO.ui.IconWidget( { icon: 'live-broadcast' } );
	this.label = new OO.ui.LabelWidget();

	this.$element.addClass( 'mw-ge-suggestededits-articleCountWidget' );
	this.$element.append( this.icon.$element, this.label.$element );
}

OO.inheritClass( ArticleCountWidget, OO.ui.Widget );

/**
 * Change the number displayed in the widget.
 *
 * @param {number} count
 */
ArticleCountWidget.prototype.setCount = function ( count ) {
	this.label.setLabel( new OO.ui.HtmlSnippet(
		mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-article-count' )
			.params( [ mw.language.convertNumber( count ) ] )
			.parse()
	) );
};

module.exports = ArticleCountWidget;
