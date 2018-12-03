( function () {

	/**
	 * Display a series of dot that are filled for previous
	 * and current panels, and empty for next panels
	 * of the stack layout.
	 *
	 * @param {OO.ui.StackLayout} stackLayout
	 * @param {Object} [config]
	 * @constructor
	 */
	function StackPositionIndicatorWidget( stackLayout, config ) {
		StackPositionIndicatorWidget.parent.call( this, config );
		this.stackLayout = stackLayout;
		this.stackLayout.connect( this, { set: 'updatePosition' } );
		this.$steps = this.stackLayout.getItems().map( function () {
			return $( '<div>' ).addClass( 'stack-position-indicator-step' );
		} );
		this.$element.addClass( 'stack-position-indicator' ).append( this.$steps );
		this.updatePosition();
	}
	OO.inheritClass( StackPositionIndicatorWidget, OO.ui.Widget );

	/**
	 * Called when the current element of the stack layout changes.
	 * Toggle the appearance of the dots based on the position
	 * of the current panel in the stack layout.
	 */
	StackPositionIndicatorWidget.prototype.updatePosition = function () {
		var items = this.stackLayout.getItems(),
			current = this.stackLayout.getCurrentItem(),
			index = items.indexOf( current ),
			i;
		for ( i = 0; i < this.$steps.length; i++ ) {
			this.$steps[ i ].toggleClass( 'stack-position-indicator-step-on', i <= index );
		}
	};

	OO.setProp(
		mw, 'libs', 'ge', 'WelcomeSurvey', 'StackPositionIndicatorWidget',
		StackPositionIndicatorWidget
	);

}() );
