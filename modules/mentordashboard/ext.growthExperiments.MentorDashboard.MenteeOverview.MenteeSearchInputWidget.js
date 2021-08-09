( function () {
	'use strict';

	function MenteeSearchInputWidget( config ) {
		config = $.extend( {
			icon: 'search',
			placeholder: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-search-placeholder' ),
			limit: 10
		}, config, { autocomplete: false } );

		MenteeSearchInputWidget.super.call( this, config );

		// api URL
		this.apiUrl = [
			mw.util.wikiScript( 'rest' ),
			'growthexperiments',
			'v0',
			'mentees',
			'prefixsearch'
		].join( '/' );
		this.limit = config.limit;
	}

	OO.inheritClass( MenteeSearchInputWidget, mw.widgets.UserInputWidget );

	MenteeSearchInputWidget.prototype.getLookupRequest = function () {
		var inputValue = this.value;

		return $.getJSON( this.apiUrl + '/' + inputValue[ 0 ].toUpperCase() + inputValue.slice( 1 ) + '?limit=' + this.limit );
	};

	MenteeSearchInputWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
		return response.usernames;
	};

	MenteeSearchInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
		var i, user,
			items = [];
		for ( i = 0; i < data.length; i++ ) {
			user = data[ i ] || {};
			items.push( new OO.ui.MenuOptionWidget( {
				label: user,
				data: user
			} ) );
		}
		return items;
	};

	MenteeSearchInputWidget.prototype.onLookupMenuChoose = function ( item ) {
		// Taken from CentralAuth's modules/ext.widgets.GlobalUserInputWidget.js
		this.closeLookupMenu();
		this.setLookupsDisabled( true );
		this.setValue( item.getData() );
		this.setLookupsDisabled( false );

		// MenteeOverview's code only processes search when enter was submitted
		// instead of wiring to multiple events, just pretend user also hit enter
		this.emit( 'enter' );
	};

	module.exports = MenteeSearchInputWidget;

}() );
