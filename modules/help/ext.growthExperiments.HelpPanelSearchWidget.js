( function () {

	/**
	 * @class mw.libs.ge.HelpPanelSearchWidget
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {mw.libs.ge.HelpPanelLogger} logger
	 * @param {Object} config
	 * @cfg {number[]} searchNamespaces Namespace IDs to include in the search
	 * @cfg {string} foreignApi api.php URL of a foreign wiki to search instead of the local wiki
	 */
	function HelpPanelSearchWidget( logger, config ) {
		HelpPanelSearchWidget.super.call( this, config );

		this.logger = logger;
		this.searchNamespaces = config.searchNamespaces;

		this.api = config.foreignApi ?
			new mw.ForeignApi( config.foreignApi, { anonymous: true } ) :
			new mw.Api();

		this.searchInput = new OO.ui.SearchInputWidget( {
			autocomplete: false,
			// MW Search doesn't have the "did you mean..?" feature.
			// Enabling spellcheck may help get better results by having fewer typos
			spellcheck: true
		} );

		this.searchResultsPanel = new OO.ui.Widget();

		this.noResultsMessage = $( '<p>' ).text(
			mw.message( 'growthexperiments-help-panel-search-no-results' ).text()
		);

		this.searchInput.connect( this, { change: 'onSearchInputChange' } );
		this.$element.append( this.searchInput.$element, this.searchResultsPanel.$element );
	}
	OO.inheritClass( HelpPanelSearchWidget, OO.ui.Widget );

	HelpPanelSearchWidget.prototype.setLoading = function ( loading ) {
		if ( loading ) {
			if ( !this.searchInput.isPending() ) {
				this.searchInput.pushPending();
			}
		} else {
			this.searchInput.popPending();
		}
	};

	HelpPanelSearchWidget.prototype.onSearchInputChange = function () {
		var query = this.searchInput.getValue();
		this.api.abort();
		this.searchResultsPanel.$element.empty();

		if ( query === '' ) {
			this.emit( 'clear' );
			return;
		}

		this.setLoading( true );
		this.api.get( {
			action: 'query',
			list: 'search',
			srnamespace: this.searchNamespaces.join( '|' ),
			srwhat: 'text',
			srprop: 'snippet',
			srsearch: query
		} ).then( function ( response ) {
			this.logger.log( 'search', {
				queryLength: query.length,
				resultCount: response.query.search.length
			} );
			this.searchResultsPanel.$element.empty();
			if ( response.query.search.length ) {
				this.searchResultsPanel.$element.append(
					response.query.search.map( this.buildSearchResult )
				);
			} else {
				this.searchResultsPanel.$element.append( this.noResultsMessage );
			}
		}.bind( this ) ).always( function () {
			this.setLoading( false );
		}.bind( this ) );
	};

	HelpPanelSearchWidget.prototype.buildSearchResult = function ( result, index ) {
		var title = mw.Title.newFromText( result.title ),
			$link = $( '<a>' )
				.text( result.title )
				.attr( {
					href: title.getUrl(),
					target: '_blank',
					'data-link-id': 'search-result-' + ( index + 1 )
				} ),
			$snippet = $( '<div>' ).append( result.snippet + mw.message( 'ellipsis' ).escaped() );

		return $( '<div>' )
			.addClass( 'mw-ge-help-panel-popup-search-search-result' )
			.append( $link, $snippet );
	};

	module.exports = HelpPanelSearchWidget;

}() );
