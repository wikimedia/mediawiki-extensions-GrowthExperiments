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

	this.animatedIcon = new OO.ui.IconWidget( { icon: 'live-broadcast-anim', classes: [ 'live-broadcast-anim' ] } );
	this.icon = new OO.ui.IconWidget( { icon: 'live-broadcast' } );
	this.label = new OO.ui.LabelWidget();

	this.$element.addClass( 'mw-ge-suggestededits-articleCountWidget' );
	this.$element.append( this.icon.$element, this.animatedIcon.$element, this.label.$element );
}

OO.inheritClass( ArticleCountWidget, OO.ui.Widget );

/**
 * Change icons based on count value
 *
 * @param {boolean} True when search results have not been returned
 */
ArticleCountWidget.prototype.toggleIcon = function ( searching ) {
	this.icon.toggle( !searching );
	this.animatedIcon.toggle( searching );
};

/**
 * Set searching to true, update label, icons
 */
ArticleCountWidget.prototype.setSearching = function () {
	this.toggleIcon( true );
	this.setLabel(
		mw.message( 'ellipsis' ).parse()
	);
};

/**
 * Change the state of searching and update count.
 *
 * @param {number} count
 */
ArticleCountWidget.prototype.setCount = function ( count ) {
	if ( count < 0 ) {
		return;
	}
	this.toggleIcon( false );
	this.setLabel(
		mw.message( 'growthexperiments-homepage-suggestededits-difficulty-filters-article-count' )
			.params( [ mw.language.convertNumber( count ) ] )
			.parse()
	);
};

/**
 * Change the label based on search results
 *
 *  @param {string} A parsed mw.message
 */
ArticleCountWidget.prototype.setLabel = function ( labelMessage ) {
	this.label.setLabel( new OO.ui.HtmlSnippet(
		labelMessage
	) );
};

module.exports = ArticleCountWidget;
