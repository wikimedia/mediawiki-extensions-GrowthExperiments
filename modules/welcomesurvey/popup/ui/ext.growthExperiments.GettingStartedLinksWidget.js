( function () {

	/**
	 * List of "getting started with editing" links
	 *
	 * @param {string} source Context where this widget is being used. Used
	 *  to populate the 'source' query parameter of the links.
	 * @param {Object} [config]
	 * @constructor
	 */
	function GettingStartedLinksWidget( source, config ) {
		var $links = [];
		GettingStartedLinksWidget.parent.call( this, config );

		[ 1, 2, 3, 4 ].forEach( function ( i ) {
			var text = mw.msg( 'welcomesurvey-sidebar-editing-link' + i + '-text' ),
				title = mw.msg( 'welcomesurvey-sidebar-editing-link' + i + '-title' ),
				titleObj = mw.Title.newFromText( title );
			if ( text && title && titleObj ) {
				$links.push(
					$( '<li>' ).addClass( 'mw-parser-output' ).append(
						$( '<a>' ).addClass( 'external' ).attr( {
							href: titleObj.getUrl( { source: source } ),
							target: '_blank'
						} ).text( text )
					)
				);
			}
		} );

		this.$element
			.addClass( 'welcomesurvey-gettingstarted-links' )
			.append( $links );
	}
	OO.inheritClass( GettingStartedLinksWidget, OO.ui.Widget );
	GettingStartedLinksWidget.static.tagName = 'ul';

	module.exports = GettingStartedLinksWidget;
}() );
