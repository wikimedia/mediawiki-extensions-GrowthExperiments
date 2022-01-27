/* eslint-disable no-jquery/no-global-selector */
( function () {
	'use strict';

	var MenteeOverviewApi = require( './MenteeOverviewApi.js' ),
		Pagination = require( './Pagination.js' ),
		FilterDropdown = require( './FilterDropdown.js' ),
		MenteeSearchInputWidget = require( './MenteeSearchInputWidget.js' ),
		TagsToFilterBy = require( './Tags.json' );

	/**
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} config Configuration options
	 */
	function MenteeOverview( config ) {
		MenteeOverview.super.call( this, config );

		// construct api client
		this.apiClient = new MenteeOverviewApi();

		// construct filtering interface
		this.searchInput = new MenteeSearchInputWidget();
		this.searchInput.connect( this, {
			enter: [ 'onSearchInputEnter' ]
		} );
		this.filterDropdown = new FilterDropdown();
		this.filterDropdown.connect( this, {
			submit: [ 'onFilterDropdownSubmit' ]
		} );

		// pagination widget
		this.paginationWidget = new Pagination();
		this.apiClient.setLimit( this.paginationWidget.getPageSize() );
		this.paginationWidget.connect( this, {
			previousPage: [ 'onPreviousPage' ],
			nextPage: [ 'onNextPage' ],
			pageSizeChanged: [ 'onPageSizeChanged' ]
		} );

		// construct empty screen interface
		this.$emptyScreen = $( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-mentee-overview-empty' ).append(
			$( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-mentee-overview-empty-icon' ),
			$( '<h3>' ),
			$( '<p>' )
		);
		this.$emptyScreen.hide(); // will be shown on as-needed basis by renderMenteeTable()

		// Empty $element, then construct the interface
		this.$element.html( '' );
		this.$element.append(
			new OO.ui.PopupButtonWidget( {
				icon: 'info-unpadded',
				id: 'growthexperiments-mentor-dashboard-module-mentee-overview-info-icon',
				framed: false,
				invisibleLabel: true,
				popup: {
					head: true,
					align: 'backwards',
					width: null,
					// HACK: setting label should not be necessary in theory, but the label doesn't
					//   appear without it
					label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-headline' ),
					$label: $( '<h3>' ).text( mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-headline' ) ),
					$content: $( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-info-content' ).append(
						$( '<p>' ).html( mw.message( 'growthexperiments-mentor-dashboard-mentee-overview-info-text' ).parse() ),
						$( '<h3>' ).text( mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-headline' ) ),
						$( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-overview-info-legend-content' ).append(
							this.makeLegendIcon(
								'unStar',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-star' )
							),
							this.makeLegendIcon(
								'help',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-questions' )
							),
							this.makeLegendIcon(
								'userAvatar',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-userinfo' )
							),
							this.makeLegendIcon(
								'edit',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-editcount' )
							),
							this.makeLegendIcon(
								'editUndo',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-reverts' )
							),
							this.makeLegendIcon(
								'clock',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-registration' )
							),
							this.makeLegendIcon(
								'block',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-blocks' )
							)
						)
					),
					padded: true
				}
			} ).$element,
			$( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-controls' ).append(
				this.filterDropdown.$element,
				this.searchInput.$element
			),
			this.$emptyScreen,
			$( '<table>' ).append(
				$( '<thead>' ).append(
					$( '<tr>' ).append(
						$( '<th>' )
							.attr( 'data-field', 'last_active' )
							.append( this.makeHeaderIcon(
								'userAvatar',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-userinfo' )
							) ),
						$( '<th>' )
							.attr( 'data-field', 'registration' )
							.append( this.makeHeaderIcon(
								'clock',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-registration' )
							) ),
						$( '<th>' )
							.attr( 'data-field', 'questions' )
							.append( this.makeHeaderIcon(
								'help',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-questions' )
							) ),
						$( '<th>' )
							.attr( 'data-field', 'editcount' )
							.append( this.makeHeaderIcon(
								'edit',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-editcount' )
							) ),
						$( '<th>' )
							.attr( 'data-field', 'reverted' )
							.append( this.makeHeaderIcon(
								'editUndo',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-reverts' )
							) ),
						$( '<th>' )
							.attr( 'data-field', 'blocks' )
							.append( this.makeHeaderIcon(
								'block',
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-legend-blocks' )
							) )
					)
				),
				$( '<tbody>' )
			),
			$( '<div>' ).addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-pagination' ).append(
				this.paginationWidget.$element
			)
		);
		// Init sorting
		this.$element.find( 'table > thead th' )
			// Add sort order
			.attr( 'data-order', 'neutral' )
			// Add sort classes
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort' )
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-neutral' )

			// Bind to onclick events to process sorting
			.on( 'click', function ( e ) {
				var el = e.currentTarget;
				var field = el.getAttribute( 'data-field' );

				// Determine next order
				var order = el.getAttribute( 'data-order' );
				if ( order === 'neutral' ) {
					order = 'ascending';
				} else if ( order === 'ascending' ) {
					order = 'descending';
				} else if ( order === 'descending' ) {
					order = 'neutral';
				}

				// Sort the table
				this.sortTable( field, order );
			}.bind( this ) );

		// Make first render happen
		this.renderMenteeTable();
	}

	OO.inheritClass( MenteeOverview, OO.ui.Widget );

	MenteeOverview.prototype.makeHeaderIcon = function ( iconName, description ) {
		return new OO.ui.IconWidget( {
			icon: iconName,
			title: description
		} ).$element;
	};

	MenteeOverview.prototype.makeLegendIcon = function ( iconName, description ) {
		return $( '<div>' )
			.addClass( 'growthexperiments-mentor-dashboard-overview-info-legend-content-icon' )
			.append(
				new OO.ui.IconWidget( { icon: iconName } ).$element,
				$( '<p>' ).text( description )
			);
	};

	MenteeOverview.prototype.getData = function () {
		return this.apiClient.getMenteeData();
	};

	MenteeOverview.prototype.makeValueTd = function ( value, fieldName, link ) {
		var $td = $( '<td>' )
			.attr( 'data-field', fieldName )
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-value' );
		if ( link === undefined ) {
			return $td.text( value );
		} else {
			return $td.html(
				$( '<a>' )
					.attr( 'href', link )
					.text( value )
			);
		}
	};

	MenteeOverview.prototype.sortTable = function ( field, dir ) {
		// First, make sure all items have neutral sorting class
		this.$element.find( 'table > thead th' )
			.removeClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-neutral' )
			.removeClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-ascending' )
			.removeClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-descending' )
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-neutral' );

		// Now, add right sorting class to right field
		// Ad eslint disable: false positive, variable is in selector, but not a class
		// (comes from line 52 and below)
		// eslint-disable-next-line mediawiki/class-doc
		this.$element.find( 'table > thead th[data-field="' + field + '"]' )
			.attr( 'data-order', dir )
			.removeClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-neutral' )
			.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-sort-' + dir );

		// Set sorting parameters
		var apiParams = {};
		apiParams.sortby = field;
		if ( dir === 'ascending' ) {
			apiParams.order = 'asc';
		} else if ( dir === 'descending' ) {
			apiParams.order = 'desc';
		}
		this.apiClient.applyApiParams( apiParams );

		// Re-render the table
		this.renderMenteeTable();

	};

	MenteeOverview.prototype.newStarButtonWidget = function ( userId ) {
		var menteeOverview = this;
		return this.apiClient.isMenteeStarred( userId ).then( function ( isStarred ) {
			var widget = new OO.ui.ButtonWidget( {
				framed: false,
				icon: isStarred ? 'unStar' : 'star',
				invisibleLabel: true
			} );
			widget.on( 'click', function () {
				menteeOverview.toggleMenteeStar( userId );
			} );
			return widget;
		} );
	};

	MenteeOverview.prototype.toggleMenteeStar = function ( userId ) {
		var menteeOverview = this;
		this.apiClient.isMenteeStarred( userId ).then( function ( isStarred ) {
			var promise;
			if ( isStarred ) {
				promise = menteeOverview.apiClient.unstarMentee( userId );
			} else {
				promise = menteeOverview.apiClient.starMentee( userId );
			}

			promise.then( function () {
				menteeOverview.newStarButtonWidget( userId ).then( function ( widget ) {
					$( 'tbody tr[data-user-id="' + userId + '"] .growthexperiments-mentor-dashboard-module-mentee-overview-userinfo-star' )
						.html( '' )
						.append(
							widget.$element
						);
				} );
			} );
		} );
	};

	MenteeOverview.prototype.updateNoResultsScreen = function ( hasResults ) {
		if ( hasResults ) {
			this.$emptyScreen.hide();
			this.$element.find( 'table' ).show();
			$( '.growthexperiments-mentor-dashboard-module-mentee-overview-pagination' ).show();
		} else {
			this.$emptyScreen.show();
			this.$element.find( 'table' ).hide();
			$( '.growthexperiments-mentor-dashboard-module-mentee-overview-pagination' ).hide();
		}

		if ( !this.apiClient.hasFilters() ) {
			this.$emptyScreen.find( '.growthexperiments-mentor-dashboard-mentee-overview-empty-icon' ).html(
				new OO.ui.IconWidget( {
					icon: 'clock'
				} ).$element
			);

			this.$emptyScreen.find( 'h3' ).text(
				mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-headline' )
			);
			this.$emptyScreen.find( 'p' ).text(
				mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-no-mentees-text' )
			);
		} else {
			this.$emptyScreen.find( '.growthexperiments-mentor-dashboard-mentee-overview-empty-icon' ).html(
				new OO.ui.IconWidget( {
					icon: 'error'
				} ).$element
			);

			this.$emptyScreen.find( 'h3' ).text(
				mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-headline' )
			);
			this.$emptyScreen.find( 'p' ).text(
				mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-empty-screen-filters-text' )
			);
		}
	};

	MenteeOverview.prototype.renderMenteeTable = function () {
		// Make sure apiClient knows which page to return
		this.apiClient.setPage( this.paginationWidget.getCurrentPage() - 1 );

		// Render the table
		var menteeOverview = this;
		this.getData().then( function ( data ) {
			if ( data.length === 0 ) {
				menteeOverview.updateNoResultsScreen( false );
				return;
			}

			var $menteeTable = $( '<tbody>' );
			Object.keys( data ).forEach( function ( ordinalId ) {
				var userData = data[ ordinalId ];

				var userId = userData.user_id;
				var $starMenteeDiv = $( '<div>' )
					.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-userinfo-star' );
				$menteeTable.append(
					$( '<tr>' ).attr( 'data-user-id', userId ).append(
						$( '<td>' ).attr( 'data-field', 'last_active' ).append(
							$( '<div>' )
								.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-userinfo-outer-container' )
								.append(
									$starMenteeDiv,
									$( '<div>' )
										.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-userinfo-inner-container' )
										.append(
											$( '<span>' )
												.append(
													$( '<a>' )
														.addClass(
															userData.userpage_exists === false ? 'new' : ''
														)
														.attr(
															'href',
															( new mw.Title( userData.username, 2 ) )
																.getUrl()
														)
														.text( userData.username )
												),
											$( '<span>' )
												.addClass( 'growthexperiments-mentor-dashboard-module-mentee-overview-table-activity' )
												.text( mw.msg(
													'growthexperiments-mentor-dashboard-mentee-overview-active-ago',
													userData.last_active.human
												) )
										)
								)
						),
						menteeOverview.makeValueTd(
							userData.registration !== null ?
								userData.registration.human :
								mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-registered-unknown' ),
							'registration'
						),
						menteeOverview.makeValueTd(
							mw.language.convertNumber( userData.questions ),
							'questions',
							mw.util.getUrl(
								'Special:Contributions/' + userData.username,
								{
									tagfilter: TagsToFilterBy.questions.join( '|' )
								}
							)
						),
						menteeOverview.makeValueTd(
							mw.language.convertNumber( userData.editcount ),
							'editcount',
							mw.util.getUrl( 'Special:Contributions/' + userData.username )
						),
						menteeOverview.makeValueTd(
							mw.language.convertNumber( userData.reverted ),
							'reverted',
							mw.util.getUrl(
								'Special:Contributions/' + userData.username,
								{
									tagfilter: TagsToFilterBy.reverted.join( '|' )
								}
							)
						),
						menteeOverview.makeValueTd(
							mw.language.convertNumber( userData.blocks ),
							'blocks',
							mw.util.getUrl(
								'Special:Log/block',
								{
									page: 'User:' + userData.username
								}
							)
						)
					)
				);

				menteeOverview.newStarButtonWidget( userId ).then(
					function ( inner$starMenteeDiv ) {
						return function ( widget ) {
							inner$starMenteeDiv.append( widget.$element );
						};
					}( $starMenteeDiv )
				);
			} );

			// update the table itself
			menteeOverview.$element.find( 'tbody' ).replaceWith( $menteeTable );
			menteeOverview.updateNoResultsScreen( true );

			// update pagination info
			menteeOverview.paginationWidget.setTotalPages(
				menteeOverview.apiClient.getTotalPages()
			);
		} );
	};

	MenteeOverview.prototype.onPreviousPage = function () {
		if ( this.paginationWidget.getCurrentPage() > 0 ) {
			this.paginationWidget.previousPage();
			this.renderMenteeTable();
		}
	};

	MenteeOverview.prototype.onNextPage = function () {
		if ( this.paginationWidget.getCurrentPage() < this.apiClient.getTotalPages() ) {
			this.paginationWidget.nextPage();
			this.renderMenteeTable();
		}
	};

	MenteeOverview.prototype.onPageSizeChanged = function ( pageSize ) {
		this.apiClient.setLimit( pageSize );

		// Rerender the table, because the page size was changed
		this.renderMenteeTable();
	};

	MenteeOverview.prototype.onFilterDropdownSubmit = function ( filters ) {
		this.paginationWidget.resetPagination(); // reset pagination (T289916)
		this.apiClient.setFilters( filters );

		// Rerender the table
		this.renderMenteeTable();
	};

	MenteeOverview.prototype.onSearchInputEnter = function () {
		this.paginationWidget.resetPagination(); // reset pagination (T289916)
		this.apiClient.setPrefix( this.searchInput.value );
		this.renderMenteeTable();
	};

	function initMenteeOverview( $table ) {
		return new MenteeOverview( {
			$element: $table
		} );
	}

	module.exports = initMenteeOverview( $( '.growthexperiments-mentor-dashboard-module-mentee-overview-content' ) );

}() );
