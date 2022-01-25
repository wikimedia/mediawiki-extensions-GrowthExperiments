module.exports = ( function () {
	'use strict';

	var hasHeroImage = false,
		StructuredTaskOnboardingContent = require( '../StructuredTaskOnboardingContent.js' ),
		content = new StructuredTaskOnboardingContent( 'addimage-onboarding-content' ),
		panelData = {};

	/**
	 * Make a paragraph element
	 *
	 * @param {string|jQuery} text Text or element with with to make a paragraph
	 * @param {string[]} extraClasses
	 * @return {jQuery}
	 */
	function makeParagraph( text, extraClasses ) {
		extraClasses = extraClasses || [];
		// The following classes are used here:
		// * addimage-onboarding-content-paragraph
		// * addimage-onboarding-content-paragraph--italic
		var $paragraph = $( '<p>' ).addClass( [ 'addimage-onboarding-content-paragraph' ].concat( extraClasses ) );
		if ( typeof text === 'string' ) {
			$paragraph.text( text );
		} else {
			$paragraph.append( text );
		}
		return $paragraph;
	}

	/**
	 * Return content element for the introduction panel
	 *
	 * @return {jQuery}
	 */
	function getIntroContent() {
		var paragraphs = [
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-intro-body-paragraph1'
			).text() ),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-intro-body-paragraph2'
			).text() ),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-intro-body-paragraph3'
			).text(), [
				'addimage-onboarding-content-paragraph--italic',
				'addimage-onboarding-content-paragraph--subtext'
			] )
		];
		return $( '<div>' ).append( paragraphs );
	}

	/**
	 * Return content element for the image details panel
	 *
	 * @return {jQuery}
	 */
	function getImageDetailsContent() {
		return $( '<div>' ).append( [
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-imagedetails-body-paragraph1' )
				.text()
			),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-imagedetails-body-paragraph2' )
				.text()
			)
		] );
	}

	/**
	 * Return content element for the article panel
	 *
	 * @return {jQuery}
	 */
	function getArticleContent() {
		return $( '<div>' ).append(
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-article-body'
			).text() )
		);
	}

	/**
	 * Return content element for the decision panel
	 *
	 * @return {jQuery}
	 */
	function getDecisionContent() {
		return $( '<div>' ).append( [
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-decision-body-paragraph1' )
				.text()
			),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-decision-body-paragraph2' )
				.text()
			)
		] );
	}

	/**
	 * Construct localized title and content for each panel
	 */
	function constructPanelData() {
		var heroImageModifier = OO.ui.isMobile() ? '' : '--desktop';
		panelData = {
			intro: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-intro-title'
				).text(),
				$content: getIntroContent(),
				heroImageClassName: 'addimage-onboarding-content-image1' + heroImageModifier
			},
			imageDetails: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-imagedetails-title'
				).text(),
				$content: getImageDetailsContent(),
				heroImageClassName: 'addimage-onboarding-content-image2' + heroImageModifier
			},
			article: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-article-title'
				).text(),
				$content: getArticleContent(),
				heroImageClassName: 'addimage-onboarding-content-image3' + heroImageModifier
			},
			decision: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-decision-title'
				).text(),
				$content: getDecisionContent(),
				heroImageClassName: 'addimage-onboarding-content-image4' + heroImageModifier
			}
		};
	}

	/**
	 * Create an OOUI PanelLayout object from the specified data
	 *
	 * @param {Object} data Object from which to create the panel
	 * @return {OO.ui.PanelLayout}
	 */
	function createPanelFromData( data ) {
		var heroImageClassName = hasHeroImage ? data.heroImageClassName : '';
		return content.createPanel( data.title, data.$content, heroImageClassName );
	}

	return {
		/**
		 * Return an array of OOUI PanelLayouts for Add Image onboarding screens
		 *
		 * @param {Object} [config]
		 * @param {boolean} [config.includeImage] Whether the panel content includes an image
		 * @return {OO.ui.PanelLayout[]}
		 */
		getPanels: function ( config ) {
			hasHeroImage = config && config.includeImage;
			constructPanelData();
			return [
				createPanelFromData( panelData.intro ),
				createPanelFromData( panelData.imageDetails ),
				createPanelFromData( panelData.article ),
				createPanelFromData( panelData.decision )
			];
		}
	};
}() );
