( function () {

	/**
	 * Widget with previous and next buttons to
	 * navigate the panels of a stack layout.
	 *
	 * @param {OO.ui.StackLayout} stackLayout
	 * @param {Object} [config]
	 * @constructor
	 */
	function StackNavigatorWidget( stackLayout, config ) {
		StackNavigatorWidget.parent.call( this, config );

		this.stackLayout = stackLayout;
		this.previousButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'welcomesurvey-back-btn' )
		} );
		this.nextButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'welcomesurvey-next-btn' ),
			flags: [ 'primary', 'progressive' ]
		} );

		this.stackLayout.connect( this, { set: 'updateButtonsState' } );
		this.previousButton.connect( this, { click: [ 'navigate', -1 ] } );
		this.nextButton.connect( this, { click: [ 'navigate', 1 ] } );

		this.$element.append( new OO.ui.HorizontalLayout( {
			items: [ this.previousButton, this.nextButton ]
		} ).$element );

		this.updateButtonsState();
	}
	OO.inheritClass( StackNavigatorWidget, OO.ui.Widget );

	/**
	 * Show the panel being "delta" positions away from
	 * the current panel in the stack layout.
	 * @param {int} delta Number of positions to go forward (positive number)
	 *  or backward (negative number).
	 */
	StackNavigatorWidget.prototype.navigate = function ( delta ) {
		var items = this.stackLayout.getItems(),
			current = this.stackLayout.getCurrentItem(),
			lastIndex = items.length - 1,
			index = items.indexOf( current );

		index += delta;
		index = Math.min( index, lastIndex );
		index = Math.max( index, 0 );
		this.stackLayout.setItem( items[ index ] );
	};

	/**
	 * Show/hide the previous and next buttons
	 * based on whether it is currently possible to navigate
	 * back or forward.
	 */
	StackNavigatorWidget.prototype.updateButtonsState = function () {
		var items = this.stackLayout.getItems(),
			current = this.stackLayout.getCurrentItem(),
			lastIndex = items.length - 1,
			index = items.indexOf( current );
		this.previousButton.toggle( index !== 0 );
		this.nextButton.toggle( index !== lastIndex );
	};

	module.exports = StackNavigatorWidget;
}() );
