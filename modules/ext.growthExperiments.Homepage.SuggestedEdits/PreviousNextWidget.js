( function () {

	function SuggestedEditsPreviousNextWidget( config ) {
		SuggestedEditsPreviousNextWidget.super.call( this, Object.assign( {}, config, {
			icon: 'arrow' + config.direction,
			framed: false,
			disabled: true,
			invisibleLabel: true,
			label: mw.message(
				// The following messages are used here:
				// * growthexperiments-homepage-suggestededits-previous-card
				// * growthexperiments-homepage-suggestededits-next-card
				'growthexperiments-homepage-suggestededits-' + config.direction.toLowerCase() + '-card',
			).text(),
		} ) );
	}

	OO.inheritClass( SuggestedEditsPreviousNextWidget, OO.ui.ButtonWidget );

	module.exports = SuggestedEditsPreviousNextWidget;

}() );
