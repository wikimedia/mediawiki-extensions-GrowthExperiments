function StructuredTaskOnboardingContent( baseClass ) {
	this.baseClass = baseClass;
}

/**
 * Create an OOUI PanelLayout with the specified title and content
 *
 * @param {string} title Localized text for panel title
 * @param {jQuery} $content Content for the panel
 * @param {string} [heroImageClassName] Class name for the image to be shown with the panel
 * @return {OO.ui.PanelLayout}
 */
StructuredTaskOnboardingContent.prototype.createPanel = function (
	title, $content, heroImageClassName
) {
	var $heroElement = heroImageClassName ?
		// eslint-disable-next-line mediawiki/class-doc
		$( '<div>' ).addClass( [
			heroImageClassName,
			'structuredtask-onboarding-content-image'
		] ) : '';
	// eslint-disable-next-line mediawiki/class-doc
	return new OO.ui.PanelLayout( {
		content: [
			$heroElement,
			$( '<div>' ).addClass( 'structuredtask-onboarding-content-title' ).text( title ),
			$content.addClass( 'structuredtask-onboarding-content-body' )
		],
		padded: true,
		classes: [
			this.baseClass,
			'structuredtask-onboarding-content',
			OO.ui.isMobile() ?
				'structuredtask-onboarding-content-mobile' :
				'structuredtask-onboarding-content-desktop'
		],
		data: {}
	} );
};

module.exports = StructuredTaskOnboardingContent;
