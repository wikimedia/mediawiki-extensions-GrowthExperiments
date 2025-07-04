( function () {

	/**
	 * @class mw.libs.ge.HelpPanelSearchWidget
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {mw.libs.ge.HelpPanelLogger} logger
	 * @param {Object} config
	 * @param {number[]} config.searchNamespaces Namespace IDs to include in the search
	 * @param {string} config.foreignApi api.php URL of a foreign wiki to search instead of the local wiki
	 */
	function HelpPanelSearchWidget( logger, config ) {
		HelpPanelSearchWidget.super.call( this, config );

		this.logger = logger;
		this.searchNamespaces = config.searchNamespaces;

		this.foreignApi = config.foreignApi;
		this.apiPromise = null;

		this.searchInput = new OO.ui.SearchInputWidget( {
			autocomplete: false,
			// We are not using the query rewrite feature ("Did you mean...?").
			// Enabling spellcheck may help get better results by having fewer typos
			spellcheck: true
		} );

		this.searchResultsPanel = new OO.ui.Widget();

		this.$noResultsMessage = $( '<p>' ).text(
			mw.message( 'growthexperiments-help-panel-search-no-results' ).text()
		);

		this.searchInput.$input.on( 'focus', ( event ) => {
			if ( event.isTrigger === undefined ) {
				// isTrigger will be undefined if it's a user-initiated action (click).
				this.logger.log( 'search-focus' );
			}
		} );

		this.searchInput.connect( this, { change: 'onSearchInputChange' } );
		this.$element.append( this.searchInput.$element, this.searchResultsPanel.$element );
	}
	OO.inheritClass( HelpPanelSearchWidget, OO.ui.Widget );

	/** events  */

	/**
	 * @event enterSearch
	 *
	 * Entering search mode
	 */

	/**
	 * @event leaveSearch
	 *
	 * Leaving search mode
	 */

	HelpPanelSearchWidget.prototype.getApi = function () {
		if ( !this.apiPromise ) {
			if ( this.foreignApi ) {
				this.apiPromise = mw.loader.using( 'mediawiki.ForeignApi' ).then( () => {
					this.api = new mw.ForeignApi( this.foreignApi, { anonymous: true } );
				} );
			} else {
				this.api = new mw.Api();
				this.apiPromise = $.Deferred().resolve().promise();
			}
		}
		return this.apiPromise;

	};

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
		const query = this.searchInput.getValue();
		if ( this.api ) {
			this.api.abort();
		}
		this.searchResultsPanel.$element.empty();

		if ( query === '' ) {
			this.emit( 'leaveSearch' );
			return;
		}

		this.emit( 'enterSearch' );
		this.setLoading( true );
		this.getApi().then( () => {
			this.api.get( {
				action: 'query',
				list: 'search',
				srnamespace: this.searchNamespaces,
				srwhat: 'text',
				srprop: 'snippet',
				srsearch: query
			} ).then( ( response ) => {
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
					this.searchResultsPanel.$element.append( this.$noResultsMessage );
				}
			} ).always( () => {
				this.setLoading( false );
			} );
		} );
	};

	HelpPanelSearchWidget.prototype.buildSearchResult = function ( result, index ) {
		const title = mw.Title.newFromText( result.title ),
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

	/**
	 * Toggle the search results panel and clear the input as needed
	 *
	 * @param {boolean} toggle
	 */
	HelpPanelSearchWidget.prototype.toggleSearchResults = function ( toggle ) {
		this.searchResultsPanel.toggle( toggle );
		if ( !toggle ) {
			// Set value directly on the inner $input to avoid events
			this.searchInput.$input.val( '' );
		}
	};

	module.exports = HelpPanelSearchWidget;

}() );
