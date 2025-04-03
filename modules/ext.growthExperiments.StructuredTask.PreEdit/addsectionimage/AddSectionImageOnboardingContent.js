module.exports = ( function () {
	'use strict';

	let hasHeroImage = false;
	const userName = mw.user.getName(),
		StructurtedTaskOnboardingContent = require( '../StructuredTaskOnboardingContent.js' ),
		content = new StructurtedTaskOnboardingContent( 'addsectionimage-onboarding-content' );

	/**
	 * Get the class name for the corresponding hero image for the specified panel
	 *
	 * @param {number} panelNumber Panel for which the hero image class is for
	 * @return {string}
	 */
	function getHeroClass( panelNumber ) {
		const heroImageModifier = OO.ui.isMobile() ? '' : '--desktop';
		if ( !hasHeroImage ) {
			return '';
		}
		// The following classes are used here:
		// * addsectionimage-onboarding-content-image1
		// * addsectionimage-onboarding-content-image2
		// * addsectionimage-onboarding-content-image3
		// * addsectionimage-onboarding-content-image4
		// * addsectionimage-onboarding-content-image1--desktop
		// * addsectionimage-onboarding-content-image2--desktop
		// * addsectionimage-onboarding-content-image3--desktop
		// * addsectionimage-onboarding-content-image4--desktop
		return 'addsectionimage-onboarding-content-image' + panelNumber + heroImageModifier;
	}

	/**
	 * Get the alt text for the corresponding hero image for the specified panel
	 *
	 * @param {number} panelNumber Panel for which the hero image class is for
	 * @return {string}
	 */
	function getImageAltText( panelNumber ) {
		// The following messages are used here:
		// * growthexperiments-addsectionimage-onboarding-content-step1-alt-text
		// * growthexperiments-addsectionimage-onboarding-content-step2-alt-text
		// * growthexperiments-addsectionimage-onboarding-content-step3-alt-text
		// * growthexperiments-addsectionimage-onboarding-content-step4-alt-text
		return mw.message( 'growthexperiments-addsectionimage-onboarding-content-step' + panelNumber + '-alt-text' ).text();
	}

	/**
	 * Create an OOUI PanelLayout with a title and an arbitrary number of paragraphs
	 *
	 * @param {number} id
	 * @param {string} title
	 * @param {Array<string>} paragraphs
	 * @param {?Array<number>} subtleParagraphIndices The indices of the paragraphs which should be rendered
	 * with subtle styles.
	 * @return {OO.ui.PanelLayout}
	 */
	function createPanel( id, title, paragraphs, subtleParagraphIndices ) {
		subtleParagraphIndices = subtleParagraphIndices || [];
		const $content = $( '<div>' ).append( paragraphs.map( ( paragraphText, index ) => {
			const $p = $( '<p>' ).text( paragraphText );
			if ( subtleParagraphIndices.includes( index ) ) {
				$p.addClass( 'addsectionimage-onboarding-content--color-subtle' );
			}
			return $p;
		} ) );
		return content.createPanel( title, $content, getHeroClass( id ), getImageAltText( id ) );
	}

	return {
		/**
		 * Return an array of OOUI PanelLayouts for Add a Link onboarding screens
		 *
		 * @param {Object} [config]
		 * @param {boolean} [config.includeImage] Whether the panel content includes an image
		 * @return {OO.ui.PanelLayout[]}
		 */
		getPanels: function ( config ) {
			hasHeroImage = config && config.includeImage;
			return [
				createPanel(
					1,
					mw.message( 'growthexperiments-addsectionimage-onboarding-content-step1-title' ).text(),
					[
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step1-body-paragraph1', userName ).text(),
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step1-body-paragraph2' ).text(),
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step1-body-paragraph3' ).text()
					],
					[
						2
					]
				),
				createPanel(
					2,
					mw.message( 'growthexperiments-addsectionimage-onboarding-content-step2-title' ).text(),
					[
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step2-body-paragraph1' ).text(),
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step2-body-paragraph2', userName ).text()
					]
				),
				createPanel(
					3,
					mw.message( 'growthexperiments-addsectionimage-onboarding-content-step3-title' ).text(),
					[
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step3-body-paragraph1' ).text()
					]
				),
				createPanel(
					4,
					mw.message( 'growthexperiments-addsectionimage-onboarding-content-step4-title' ).text(),
					[
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step4-body-paragraph1', userName ).text(),
						mw.message( 'growthexperiments-addsectionimage-onboarding-content-step4-body-paragraph2', userName ).text()
					]
				)
			];
		}
	};
}() );
