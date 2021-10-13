module.exports = ( function () {
	'use strict';

	var hasHeroImage = false,
		userName = mw.user.getName(),
		TaskTypesAbFilter = require( '../../homepage/suggestededits/TaskTypesAbFilter.js' ),
		taskTypes = TaskTypesAbFilter.filterTaskTypes( require( '../TaskTypes.json' ) ),
		taskTypeData = taskTypes[ 'image-recommendation' ] || {},
		StructuredTaskOnboardingContent = require( '../StructuredTaskOnboardingContent.js' ),
		content = new StructuredTaskOnboardingContent( 'addimage-onboarding-content' ),
		panelData = {};

	/**
	 * Make a paragraph element
	 *
	 * @param {string|jQuery} text Text or element with with to make a paragraph
	 * @return {jQuery}
	 */
	function makeParagraph( text ) {
		var $paragraph = $( '<p>' ).addClass( 'addimage-onboarding-content-paragraph' );
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
			).params( [ userName ] ).text() ),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-intro-body-paragraph2'
			).params( [ userName ] ).text() )
		];
		var learnMoreLinkUrl = taskTypeData.learnMoreLink ?
			mw.util.getUrl( taskTypeData.learnMoreLink ) :
			null;
		if ( learnMoreLinkUrl ) {
			var linkText = mw.message(
				'growthexperiments-addimage-onboarding-content-intro-learn-more-link-text'
			).text();
			paragraphs.push(
				makeParagraph(
					$( '<a>' ).text( linkText ).attr( {
						href: learnMoreLinkUrl,
						class: 'structuredtask-onboarding-content-link',
						target: '_blank'
					} )
				)
			);
		}
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
				.params( [ userName ] ).text()
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
				.params( [ userName ] ).text()
			),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-decision-body-paragraph2' )
				.params( [ userName ] ).text()
			),
			makeParagraph( mw.message(
				'growthexperiments-addimage-onboarding-content-decision-body-paragraph3' )
				.params( [ userName ] ).text()
			)
		] );
	}

	/**
	 * Construct localized title and content for each panel
	 */
	function constructPanelData() {
		panelData = {
			intro: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-intro-title'
				).text(),
				$content: getIntroContent(),
				heroImageClassName: 'addimage-onboarding-content-image1'
			},
			imageDetails: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-imagedetails-title'
				).text(),
				$content: getImageDetailsContent(),
				heroImageClassName: 'addimage-onboarding-content-image2'
			},
			article: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-article-title'
				).text(),
				$content: getArticleContent(),
				heroImageClassName: 'addimage-onboarding-content-image3'
			},
			decision: {
				title: mw.message(
					'growthexperiments-addimage-onboarding-content-decision-title'
				).text(),
				$content: getDecisionContent(),
				heroImageClassName: 'addimage-onboarding-content-image4'
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
