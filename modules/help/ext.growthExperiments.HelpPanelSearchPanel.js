( function () {

	/**
	 * @class
	 * @extends OO.ui.PanelLayout
	 *
	 * @constructor
	 * @param {Object} config
	 * @cfg {number[]} searchNamespaces Namespace IDs to include in the search
	 * @cfg {boolean} devMode Search enwiki instead of the current wiki to get more/better results
	 */
	function HelpPanelSearchPanel( config ) {
		HelpPanelSearchPanel.super.call( this, config );

		this.searchNamespaces = config.searchNamespaces;

		this.api = config.devMode ?
			new mw.ForeignApi(
				'https://en.wikipedia.org/w/api.php',
				{ anonymous: true }
			) :
			new mw.Api();

		this.searchInput = new OO.ui.SearchInputWidget( {
			autocomplete: false,
			// MW Search doesn't have the "did you mean..?" feature.
			// Enabling spellcheck may help get better results by having fewer typos
			spellcheck: true
		} );
		this.searchResultsPanel = new OO.ui.PanelLayout( {
			padded: false,
			expanded: false
		} );

		this.searchInput.connect( this, { change: 'onSearchInputChange' } );

		this.$element
			.addClass( 'helppanel-searchpanel' )
			.append(
				new OO.ui.FieldsetLayout( {
					items: [
						new OO.ui.FieldLayout(
							new OO.ui.Widget( {
								content: [
									this.searchInput,
									this.searchResultsPanel
								] } ),
							{
								align: 'top',
								label: mw.message( 'growthexperiments-help-panel-search-label' ).text(),
								classes: [ 'mw-ge-help-panel-popup-search' ]
							}
						)
					]
				} ).$element
			);
	}
	OO.inheritClass( HelpPanelSearchPanel, OO.ui.PanelLayout );

	HelpPanelSearchPanel.prototype.setLoading = function ( loading ) {
		if ( loading ) {
			if ( !this.searchInput.isPending() ) {
				this.searchInput.pushPending();
			}
		} else {
			this.searchInput.popPending();
		}
	};

	HelpPanelSearchPanel.prototype.onSearchInputChange = function () {
		var query = this.searchInput.getValue();
		this.api.abort();
		this.searchResultsPanel.$element.empty();

		if ( query === '' ) {
			// todo: this works for when the user clears the content by clicking
			// on the indicator but has unintended consequences when the user
			// uses backspace to remove the content
			// this.emit( 'clear' );
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
			this.setLoading( false );
			this.searchResultsPanel.$element
				.empty()
				.append( response.query.search.map( function ( result ) {
					var title = mw.Title.newFromText( result.title ),
						$link = $( '<a>' )
							.text( result.title )
							.attr( {
								href: title.getUrl(),
								target: '_blank'
							} ),
						$snippet = $( '<div>' ).append( result.snippet );

					return $( '<div>' )
						.addClass( 'mw-ge-help-panel-popup-search-search-result' )
						.append( $link, $snippet );
				} ) );
		}.bind( this ) );
	};

	OO.setProp( mw, 'libs', 'ge', 'HelpPanelSearchPanel', HelpPanelSearchPanel );

}() );
