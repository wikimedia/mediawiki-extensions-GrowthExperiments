( function () {
	'use strict';

	function MenteeOverviewPagination( config ) {
		config = $.extend( {
			pageSize: 10,
			currentPage: 1,
			totalPages: 1
		}, config );

		MenteeOverviewPagination.super.call( this, config );

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

		this.pageSize = config.pageSize;
		this.currentPage = config.currentPage;
		this.totalPages = config.totalPages;
		// Will be filled with data as part of UpdatePaginationState.
		this.$pageCounterSpan = $( '<span>' );
		this.updatePaginationState();

		this.showEntriesBtn = new OO.ui.ButtonMenuSelectWidget( {
			framed: false,
			indicator: 'down',
			clearOnSelect: false,
			label: mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-show-entries', this.pageSize ),
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

	OO.inheritClass( MenteeOverviewPagination, mw.widgets.UserInputWidget );

	MenteeOverviewPagination.prototype.updatePaginationState = function () {
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

	MenteeOverviewPagination.prototype.setTotalPages = function ( value ) {
		this.totalPages = value;
		this.updatePaginationState();
	};

	MenteeOverviewPagination.prototype.getCurrentPage = function () {
		return this.currentPage;
	};

	MenteeOverviewPagination.prototype.setCurrentPage = function ( value ) {
		this.currentPage = value;
		this.updatePaginationState();
	};

	MenteeOverviewPagination.prototype.getPageSize = function () {
		return this.pageSize;
	};

	MenteeOverviewPagination.prototype.setPageSize = function ( value ) {
		this.pageSize = value;
		this.showEntriesBtn.setLabel(
			mw.msg( 'growthexperiments-mentor-dashboard-mentee-overview-show-entries', mw.language.convertNumber( this.pageSize ) )
		);
	};

	MenteeOverviewPagination.prototype.onPageSizeChanged = function ( menuOptions ) {
		this.setPageSize( menuOptions.getData() );
		this.emit( 'pageSizeChanged', this.pageSize );
	};

	MenteeOverviewPagination.prototype.onPreviousButtonClicked = function () {
		this.emit( 'previousPage' );
	};

	MenteeOverviewPagination.prototype.onNextButtonClicked = function () {
		this.emit( 'nextPage' );
	};

	MenteeOverviewPagination.prototype.previousPage = function () {
		if ( this.currentPage <= 1 ) {
			return false;
		}

		this.setCurrentPage( this.currentPage - 1 );
		return true;
	};

	MenteeOverviewPagination.prototype.nextPage = function () {
		if ( this.currentPage >= this.totalPages ) {
			return false;
		}

		this.setCurrentPage( this.currentPage + 1 );
		return true;
	};

	module.exports = MenteeOverviewPagination;

}() );
