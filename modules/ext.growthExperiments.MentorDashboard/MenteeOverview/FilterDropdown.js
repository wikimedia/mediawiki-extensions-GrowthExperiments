( function () {
	'use strict';

	var MenteeOverviewPresets = require( './MenteeOverviewPresets.js' );

	function FilterDropdown( config ) {
		FilterDropdown.super.call( this, config );

		this.presetsClient = new MenteeOverviewPresets();

		// stored in MenteeOverview's onFilterDropdownSubmit; default value provided in PHP from MentorDashboardHooks
		var savedFilters = this.presetsClient.getFilters();

		// prepare widgets that contain information we filter by
		this.filterDropdownEditsFrom = new OO.ui.NumberInputWidget( {
			showButtons: false,
			min: 0,
			step: 1,
			input: {
				value: typeof savedFilters.minedits === 'number' ? savedFilters.minedits : ''
			}
		} );
		this.filterDropdownEditsTo = new OO.ui.NumberInputWidget( {
			showButtons: false,
			min: 0,
			step: 1,
			input: {
				value: typeof savedFilters.maxedits === 'number' ? savedFilters.maxedits : ''
			}
		} );

		this.filterDropdownActiveDaysAgoValue = NaN; // stores currently selected value in days
		this.filterDropdownActiveDaysAgo = new OO.ui.ButtonSelectWidget( {
			items: [
				this.newFilterByDaysAgoOption( 1 ),
				this.newFilterByDaysAgoOption( 7 ),
				this.newFilterByDaysAgoOption( 14 )
			]
		} );
		this.filterDropdownActiveMonthsAgo = new OO.ui.ButtonSelectWidget( {
			items: [
				this.newFilterByMonthsAgoOption( 1 ),
				this.newFilterByMonthsAgoOption( 2 ),
				this.newFilterByMonthsAgoOption( 6 )
			]
		} );
		if ( typeof savedFilters.activedaysago === 'number' ) {
			this.filterDropdownActiveDaysAgoValue = savedFilters.activedaysago;
			// if savedFilters.activedaysago is an integer smaller than 30 days, the option
			// to select is in the days widget. If it is at least 30 days, it must be in the months widget.
			if ( savedFilters.activedaysago < 30 ) {
				this.filterDropdownActiveDaysAgo.selectItemByData( savedFilters.activedaysago );
			} else {
				this.filterDropdownActiveMonthsAgo.selectItemByData( savedFilters.activedaysago );
			}
		}

		this.filterDropdownOnlyStarred = new OO.ui.CheckboxInputWidget( {
			selected: typeof savedFilters.onlystarred === 'boolean' ? savedFilters.onlystarred : false
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
				$( '<h3>' ).text(
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
				$( '<h3>' ).text(
					mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-headline' )
				),
				$( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-filter-dropdown-last-active' ).append(
					new OO.ui.FieldLayout( this.filterDropdownActiveDaysAgo, {
						align: 'inline',
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-days' )
					} ).$element,
					new OO.ui.FieldLayout( this.filterDropdownActiveMonthsAgo, {
						align: 'inline',
						label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-months' )
					} ).$element
				),
				$( '<hr>' ),
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
	OO.inheritClass( FilterDropdown, OO.ui.Widget );

	FilterDropdown.prototype.newFilterByDaysAgoOption = function ( daysAgo ) {
		var btn = new OO.ui.ButtonOptionWidget( {
			data: daysAgo,
			label: mw.language.convertNumber( daysAgo ),
			title: mw.msg(
				'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-days-title',
				daysAgo,
				mw.language.convertNumber( daysAgo )
			)
		} );
		btn.connect( this, {
			click: [ 'onFilterByDaysAgoChanged' ]
		} );
		return btn;
	};

	FilterDropdown.prototype.onFilterByDaysAgoChanged = function () {
		var selectedItem = this.filterDropdownActiveDaysAgo.findSelectedItem(),
			monthsSelectedItem = this.filterDropdownActiveMonthsAgo.findSelectedItem(),
			selectedValue = selectedItem.getData();

		if ( monthsSelectedItem !== null ) {
			monthsSelectedItem.setSelected( false );
		}

		if ( selectedValue !== this.filterDropdownActiveDaysAgoValue ) {
			// user either clicked for the first time, or clicked a different option; store
			// their selection
			this.filterDropdownActiveDaysAgoValue = selectedValue;
		} else {
			// user clicked same item for the second time, reset selection
			this.filterDropdownActiveDaysAgoValue = NaN;
			selectedItem.setSelected( false );
		}
	};

	FilterDropdown.prototype.newFilterByMonthsAgoOption = function ( monthsAgo ) {
		var btn = new OO.ui.ButtonOptionWidget( {
			data: monthsAgo * 30,
			label: mw.language.convertNumber( monthsAgo ),
			title: mw.msg(
				'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-months-title',
				monthsAgo,
				mw.language.convertNumber( monthsAgo )
			)
		} );
		btn.connect( this, {
			click: [ 'onFilterByMonthsAgoChanged' ]
		} );
		return btn;
	};

	FilterDropdown.prototype.onFilterByMonthsAgoChanged = function () {
		var selectedItem = this.filterDropdownActiveMonthsAgo.findSelectedItem(),
			daysSelectedItem = this.filterDropdownActiveDaysAgo.findSelectedItem(),
			selectedValue = selectedItem.getData();

		if ( daysSelectedItem !== null ) {
			daysSelectedItem.setSelected( false );
		}

		if ( selectedValue !== this.filterDropdownActiveDaysAgoValue ) {
			// user either clicked for the first time, or clicked a different option; store
			// their selection
			this.filterDropdownActiveDaysAgoValue = selectedValue;
		} else {
			// user clicked same item for the second time, reset selection
			this.filterDropdownActiveDaysAgoValue = NaN;
			selectedItem.setSelected( false );
		}
	};

	FilterDropdown.prototype.getFilters = function () {
		var rawFilters = {
				minedits: parseInt( this.filterDropdownEditsFrom.getValue() ),
				maxedits: parseInt( this.filterDropdownEditsTo.getValue() ),
				onlystarred: this.filterDropdownOnlyStarred.selected,
				activedaysago: this.filterDropdownActiveDaysAgoValue
			},
			filters = {};

		// Do not include filters that are not set
		Object.keys( rawFilters ).forEach( function ( key ) {
			if ( isNaN( rawFilters[ key ] ) ) {
				// We do not want this filter
				return;
			}

			// Copy to filters
			filters[ key ] = rawFilters[ key ];
		} );

		return filters;
	};

	FilterDropdown.prototype.onFilterSubmitClicked = function () {
		// Emit event!
		this.emit( 'submit', this.getFilters() );

		// Close filtering popup, if opened
		if ( this.filterBtn.popup.isVisible() ) {
			this.filterBtn.popup.toggle( false );
		}
	};

	module.exports = FilterDropdown;
}() );
