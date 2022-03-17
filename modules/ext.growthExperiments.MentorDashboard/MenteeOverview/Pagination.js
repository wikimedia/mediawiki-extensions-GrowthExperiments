( function () {
	'use strict';

	var MenteeOverviewPresets = require( './MenteeOverviewPresets.js' );

	function Pagination( config ) {
		this.presetsClient = new MenteeOverviewPresets();

		this.config = $.extend( {
			pageSize: this.presetsClient.getUsersToShow(),
			currentPage: 1,
			totalPages: 1
		}, config );

		Pagination.super.call( this, this.config );

		this.prevBtn = new OO.ui.ButtonInputWidget( {
			framed: false,
			icon: 'previous'
		} );
		this.nextBtn = new OO.ui.ButtonInputWidget( {
			framed: false,
			icon: 'next'
		} );
		this.prevBtn.connect( this, { click: [ 'onPreviousButtonClicked' ] } );
		this.nextBtn.connect( this, { click: [ 'onNextButtonClicked' ] } );

		this.pageSize = this.config.pageSize;
		this.currentPage = this.config.currentPage;
		this.totalPages = this.config.totalPages;
		// Will be filled with data as part of UpdatePaginationState.
		this.$pageCounterSpan = $( '<span>' );
		this.updatePaginationState();

		this.showEntriesBtn = new OO.ui.ButtonMenuSelectWidget( {
			framed: false,
			indicator: 'down',
			clearOnSelect: false,
			label: mw.msg(
				'growthexperiments-mentor-dashboard-mentee-overview-show-entries',
				mw.language.convertNumber( this.pageSize )
			),
			menu: {
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 5,
						label: mw.language.convertNumber( 5 )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 10,
						label: mw.language.convertNumber( 10 )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 15,
						label: mw.language.convertNumber( 15 )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 20,
						label: mw.language.convertNumber( 20 )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 25,
						label: mw.language.convertNumber( 25 )
					} )
				]
			}
		} );
		this.showEntriesBtn.getMenu().connect( this, { choose: [ 'onPageSizeChanged' ] } );

		this.$element.html( '' );
		this.$element.append(
			this.showEntriesBtn.$element,
			this.$pageCounterSpan,
			this.prevBtn.$element,
			this.nextBtn.$element
		);
	}

	OO.inheritClass( Pagination, mw.widgets.UserInputWidget );

	Pagination.prototype.updatePaginationState = function () {
		this.nextBtn.setDisabled( this.currentPage >= this.totalPages );
		this.prevBtn.setDisabled( this.currentPage <= 1 );

		this.$pageCounterSpan.text(
			mw.msg(
				'growthexperiments-mentor-dashboard-mentee-overview-page-counter',
				mw.language.convertNumber( this.currentPage ),
				mw.language.convertNumber( this.totalPages )
			)
		);
	};

	Pagination.prototype.setTotalPages = function ( value ) {
		this.totalPages = value;
		this.updatePaginationState();
	};

	Pagination.prototype.getCurrentPage = function () {
		return this.currentPage;
	};

	Pagination.prototype.setCurrentPage = function ( value ) {
		this.currentPage = value;
		this.updatePaginationState();
	};

	Pagination.prototype.getPageSize = function () {
		return this.pageSize;
	};

	Pagination.prototype.setPageSize = function ( value ) {
		this.pageSize = value;
		this.showEntriesBtn.setLabel(
			mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-show-entries', mw.language.convertNumber( this.pageSize ) )
		);
	};

	Pagination.prototype.resetPagination = function () {
		this.currentPage = this.config.currentPage;
		this.totalPages = this.config.totalPages;

		this.updatePaginationState();
	};

	Pagination.prototype.onPageSizeChanged = function ( menuOptions ) {
		this.setPageSize( menuOptions.getData() );
		this.emit( 'pageSizeChanged', this.pageSize );
	};

	Pagination.prototype.onPreviousButtonClicked = function () {
		this.emit( 'previousPage' );
	};

	Pagination.prototype.onNextButtonClicked = function () {
		this.emit( 'nextPage' );
	};

	Pagination.prototype.previousPage = function () {
		if ( this.currentPage <= 1 ) {
			return false;
		}

		this.setCurrentPage( this.currentPage - 1 );
		return true;
	};

	Pagination.prototype.nextPage = function () {
		if ( this.currentPage >= this.totalPages ) {
			return false;
		}

		this.setCurrentPage( this.currentPage + 1 );
		return true;
	};

	module.exports = Pagination;

}() );
