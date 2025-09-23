( function () {
	'use strict';

	function NoResultsWidget( config ) {
		NoResultsWidget.super.call( this, config );
		const topicMatchAnd = config.topicMatching && config.topicMatchModeIsAND;
		const $card = $( '<div>' ).addClass( 'se-card-no-results' );
		let noResultsDescriptionText = 'growthexperiments-homepage-suggestededits-select-other-difficulty';
		if ( config.topicMatching ) {
			noResultsDescriptionText = 'growthexperiments-homepage-suggestededits-select-other-topics-difficulty';
			if ( topicMatchAnd ) {
				noResultsDescriptionText = 'growthexperiments-homepage-suggestededits-select-other-topic-mode';
			}
		}
		const content = [
			$( '<h3>' ).addClass( 'se-card-title' ).text(
				mw.message( 'growthexperiments-homepage-suggestededits-no-results' ).text() ),
			$( '<div>' ).addClass( 'se-card-image' ),
			$( '<p>' ).addClass( 'se-card-text' )
				// Messages that can be used here:
				// * growthexperiments-homepage-suggestededits-select-other-difficulty
				// * growthexperiments-homepage-suggestededits-select-other-topics-difficulty
				// * growthexperiments-homepage-suggestededits-select-other-topic-mode
				.text( mw.message( noResultsDescriptionText, mw.user.getName() ).text() ),
		];
		if ( topicMatchAnd ) {
			const cta = new OO.ui.ButtonWidget( {
				classes: [
					'se-card-link',
				],
				framed: false,
				flags: [
					'progressive',
				],
				icon: 'funnel',
				label: mw.message( 'growthexperiments-homepage-suggestededits-select-other-topic-mode-cta' ).text(),
			} ).on( 'click', config.setMatchModeOr );
			content.push( cta.$element );
		}
		$card.append.apply( $card, content );
		this.$element.append( $card );
	}

	OO.inheritClass( NoResultsWidget, OO.ui.Widget );

	module.exports = NoResultsWidget;
}() );
