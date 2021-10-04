module.exports = ( function () {
	'use strict';

	var hasHeroImage = false,
		userName = mw.user.getName(),
		TaskTypesAbFilter = require( '../../homepage/suggestededits/TaskTypesAbFilter.js' ),
		taskTypes = TaskTypesAbFilter.filterTaskTypes( require( '../TaskTypes.json' ) ),
		taskTypeData = taskTypes[ 'link-recommendation' ] || {};

	/**
	 * Create an OOUI PanelLayout with the specified title and content
	 *
	 * @param {string} title Localized text for panel title
	 * @param {jQuery} $content Content for the panel
	 * @param {string} [heroImageClassName] Class name for the image to be shown with the panel
	 * @return {OO.ui.PanelLayout}
	 */
	function createPanel( title, $content, heroImageClassName ) {
		// The following classes are used here:
		// * addlink-onboarding-content-image1
		// * addlink-onboarding-content-image2
		// * addlink-onboarding-content-image3
		var $heroElement = hasHeroImage && heroImageClassName ? $( '<div>' ).addClass( heroImageClassName ) : '';
		return new OO.ui.PanelLayout( {
			content: [
				$heroElement,
				$( '<div>' ).attr( 'class', 'addlink-onboarding-content-title' ).text( title ),
				$content.addClass( 'addlink-onboarding-content-body' )
			],
			padded: true,
			classes: [
				'addlink-onboarding-content',
				OO.ui.isMobile() ? 'addlink-onboarding-content-mobile' : 'addlink-onboarding-content-desktop'
			],
			data: {}
		} );
	}

	/**
	 * Get the class name for the corresponding hero image for the specified panel
	 *
	 * @param {number} panelNumber Panel for which the hero image class is for
	 * @return {string}
	 */
	function getHeroClass( panelNumber ) {
		// The following classes are used here:
		// * addlink-onboarding-content-image1
		// * addlink-onboarding-content-image2
		// * addlink-onboarding-content-image3
		return 'addlink-onboarding-content-image' + panelNumber;
	}

	/**
	 * Get a dictionary of localized texts used in the intro panel
	 *
	 * @return {Object}
	 */
	function getIntroPanelMessages() {
		return {
			title: mw.message( 'growthexperiments-addlink-onboarding-content-intro-title' ).text(),
			paragraph1: mw.message( 'growthexperiments-addlink-onboarding-content-intro-body-paragraph1', userName ).text(),
			paragraph2: mw.message( 'growthexperiments-addlink-onboarding-content-intro-body-paragraph2' ).text(),
			exampleLabel: mw.message( 'growthexperiments-addlink-onboarding-content-intro-body-example-label' ).text(),
			exampleHtml: mw.message( 'growthexperiments-addlink-onboarding-content-intro-body-example-text' ).parse()
		};
	}

	/**
	 * Create an OOUI PanelLayout for the intro panel
	 *
	 * @return {OO.ui.PanelLayout}
	 */
	function createIntroPanel() {
		var messages = getIntroPanelMessages(),
			$content = $( '<div>' ).append( [
				$( '<p>' ).text( messages.paragraph1 ),
				$( '<div>' ).attr( 'class', 'addlink-onboarding-content-example-label' ).text( messages.exampleLabel ),
				$( '<div>' ).attr( 'class', 'addlink-onboarding-content-example' ).html( messages.exampleHtml ),
				$( '<p>' ).text( messages.paragraph2 )
			] );
		return createPanel( messages.title, $content, getHeroClass( 1 ) );
	}

	/**
	 * Get a dictionary of localized texts used in the about suggested links panel
	 *
	 * @return {Object}
	 */
	function getAboutSuggestedLinksPanelMessages() {
		return {
			title: mw.message( 'growthexperiments-addlink-onboarding-content-about-suggested-links-title' ).text(),
			paragraph1: mw.message( 'growthexperiments-addlink-onboarding-content-about-suggested-links-body', userName ).text(),
			learnMoreLinkText: mw.message( 'growthexperiments-addlink-onboarding-content-about-suggested-links-body-learn-more-link-text' ).text(),
			learnMoreLinkUrl: taskTypeData.learnMoreLink ? mw.util.getUrl( taskTypeData.learnMoreLink ) : null
		};
	}

	/**
	 * Create an OOUI PanelLayout for the about suggested links panel
	 *
	 * @return {OO.ui.PanelLayout}
	 */
	function createAboutSuggestedLinksPanel() {
		var messages = getAboutSuggestedLinksPanelMessages(),
			$content = $( '<div>' ).append( $( '<p>' ).text( messages.paragraph1 ) );

		if ( messages.learnMoreLinkText && messages.learnMoreLinkUrl ) {
			$content.append( $( '<a>' ).text( messages.learnMoreLinkText ).attr( {
				href: messages.learnMoreLinkUrl,
				class: 'addlink-onboarding-content-link onboarding-content-link',
				target: '_blank'
			} ) );
		}
		return createPanel( messages.title, $content, getHeroClass( 2 ) );
	}

	/**
	 * Get a dictionary of localized texts used in the linking guidelines panel
	 *
	 * @return {Object}
	 */
	function getLinkingGuidelinesPanelMessages() {
		return {
			title: mw.message( 'growthexperiments-addlink-onboarding-content-linking-guidelines-title' ).text(),
			body: mw.message(
				'growthexperiments-addlink-onboarding-content-linking-guidelines-body',
				userName
			).parse()
		};
	}

	/**
	 * Create an OOUI PanelLayout for the linking guidelines panel
	 *
	 * @return {OO.ui.PanelLayout}
	 */
	function createLinkingGuidelinesPanel() {
		var messages = getLinkingGuidelinesPanelMessages(),
			$content = $( '<div>' ),
			$list;
		$list = $( '<ul>' ).html( messages.body ).addClass( 'addlink-onboarding-content-list' );
		$list.find( 'li' ).addClass( 'addlink-onboarding-content-list-item' );
		$content.append( $list );
		return createPanel( messages.title, $content, getHeroClass( 3 ) );
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
				createIntroPanel(),
				createAboutSuggestedLinksPanel(),
				createLinkingGuidelinesPanel()
			];
		}
	};
}() );
