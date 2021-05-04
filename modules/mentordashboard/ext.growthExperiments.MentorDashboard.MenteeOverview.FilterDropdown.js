( function () {
	'use strict';

	function MenteeOverviewFilterDropdown( config ) {
		MenteeOverviewFilterDropdown.super.call( this, config );

		// prepare widgets that contain information we filter by
		this.filterDropdownEditsFrom = new OO.ui.NumberInputWidget( {
			showButtons: false,
			min: 0,
			step: 1
		} );
		this.filterDropdownEditsTo = new OO.ui.NumberInputWidget( {
			showButtons: false,
			min: 0,
			step: 1
		} );
		this.filterDropdownOnlyStarred = new OO.ui.CheckboxInputWidget( {
			selected: false
		} );

		// prepare submit button
		this.filterDropdownSubmit = new OO.ui.ButtonWidget( {
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-submit' ),
			classes: [ 'growthexperiments-mentor-dashboard-module-mentee-overview-submit-btn' ]
		} );
		this.filterDropdownSubmit.connect( this, {
			click: [ 'onFilterSubmitClicked' ]
		} );

		// build the dropdown UI
		this.$filterDropdown = $( '<div>' )
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-filter-dropdown' )
			.append(
				$( '<h3>' ).append(
					mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-total-edits-headline' )
				),
				$( '<div>' )
					.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-filter-dropdown-controls' )
					.append(
						new OO.ui.FieldLayout( this.filterDropdownEditsFrom, {
							align: 'inline',
							label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-total-edits-from' )
						} ).$element,
						new OO.ui.FieldLayout( this.filterDropdownEditsTo, {
							align: 'inline',
							label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-total-edits-to' )
						} ).$element
					),
				$( '<hr>' ),
				$( '<h3>' ).append(
					mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-starred-headline' )
				),
				new OO.ui.FieldLayout( this.filterDropdownOnlyStarred, {
					align: 'inline',
					label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-starred-only-starred' )
				} ).$element,
				this.filterDropdownSubmit.$element
			);

		this.filterBtn = new OO.ui.PopupButtonWidget( {
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter' ),
			indicator: 'down',
			popup: {
				$content: this.$filterDropdown,
				padded: true,
				align: 'forwards'
			}
		} );

		this.$element
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-filter' )
			.html(
				this.filterBtn.$element
			);
	}
	OO.inheritClass( MenteeOverviewFilterDropdown, OO.ui.Widget );

	MenteeOverviewFilterDropdown.prototype.onFilterSubmitClicked = function () {
		var rawFilters = {
			minedits: parseInt( this.filterDropdownEditsFrom.getValue() ),
			maxedits: parseInt( this.filterDropdownEditsTo.getValue() ),
			onlystarred: this.filterDropdownOnlyStarred.selected
		};
		var filters = {};

		// Do not include filters that are not set
		Object.keys( rawFilters ).forEach( function ( key ) {
			if ( isNaN( rawFilters[ key ] ) ) {
				// We do not want this filter
				return;
			}

			// Copy to filters
			filters[ key ] = rawFilters[ key ];
		} );

		// Emit event!
		this.emit( 'submit', filters );

		// Close filtering popup, if opened
		if ( this.filterBtn.popup.isVisible() ) {
			this.filterBtn.popup.toggle( false );
		}
	};

	module.exports = MenteeOverviewFilterDropdown;
}() );
