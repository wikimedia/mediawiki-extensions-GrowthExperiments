<?php


namespace GrowthExperiments\Homepage;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Util;
use Html;
use OOUI\IconWidget;

class SiteNoticeGenerator {

	/**
	 * @param string $name
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param bool &$minervaEnableSiteNotice
	 */
	public static function setNotice( $name, &$siteNotice, \Skin $skin, &$minervaEnableSiteNotice ) {
		if ( $skin->getTitle()->isSpecial( 'WelcomeSurvey' ) ) {
			// Don't show any notices on the welcome survey.
			return;
		}
		switch ( $name ) {
			case HomepageHooks::CONFIRMEMAIL_QUERY_PARAM:
				if ( $skin->getTitle()->isSpecial( 'Homepage' ) ) {
					self::setConfirmEmailSiteNotice( $siteNotice, $skin, $minervaEnableSiteNotice );
				}
				break;
			case 'specialwelcomesurvey':
				if ( $skin->getTitle()->isSpecial( 'Homepage' ) ) {
					self::setDiscoverySiteNotice( $siteNotice, $skin, $name );
				}
				break;
			case 'welcomesurvey-originalcontext':
				if ( !$skin->getTitle()->isSpecial( 'Homepage' ) ) {
					self::setDiscoverySiteNotice( $siteNotice, $skin, $name );
				}
				break;
			default:
				self::maybeShowIfUserAbandonedWelcomeSurvey( $siteNotice, $skin );
				break;
		}
	}

	private static function maybeShowIfUserAbandonedWelcomeSurvey( &$siteNotice, \Skin $skin ) {
		if ( self::isWelcomeSurveyInReferer( $skin ) ) {
			self::setDiscoverySiteNotice(
				$siteNotice,
				$skin,
				'welcomesurvey-originalcontext'
			);
		}
	}

	private static function isWelcomeSurveyInReferer( \Skin $skin ) {
		foreach ( $skin->getLanguage()->getSpecialPageAliases()['WelcomeSurvey'] as $alias ) {
			if ( strpos( $skin->getRequest()->getHeader( 'REFERER' ), $alias ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param bool &$minervaEnableSiteNotice
	 */
	private static function setConfirmEmailSiteNotice(
		&$siteNotice, \Skin $skin, &$minervaEnableSiteNotice
	) {
		$output = $skin->getOutput();
		$output->addModules( 'ext.growthExperiments.Homepage.ConfirmEmail' );
		$output->addModuleStyles( 'ext.growthExperiments.Homepage.ConfirmEmail.styles' );
		$baseCssClassName = 'mw-ge-homepage-confirmemail-nojs';
		$cssClasses = [
			$baseCssClassName,
			Util::isMobile( $skin ) ? $baseCssClassName . '-mobile' : $baseCssClassName . '-desktop'
		];
		$siteNotice = Html::rawElement( 'div', [ 'class' => $cssClasses ],
			new IconWidget( [ 'icon' => 'check', 'flags' => 'success' ] ) . ' ' .
			Html::element( 'span', [ 'class' => 'mw-ge-homepage-confirmemail-nojs-message' ],
				$output->msg( 'confirmemail_loggedin' )->text() )
		);
		$minervaEnableSiteNotice = true;
	}

	/**
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param string $contextName
	 */
	private static function setDiscoverySiteNotice(
		&$siteNotice, \Skin $skin, $contextName
	) {
		$output = $skin->getOutput();
		$output->enableOOUI();
		$output->addModuleStyles( [
			'oojs-ui.styles.icons-user',
			'ext.growthExperiments.Homepage.Discovery.styles'
		] );
		$username = $skin->getUser()->getName();
		if ( $contextName === 'specialwelcomesurvey' ) {
			$msgHeaderKey = 'growthexperiments-homepage-discovery-banner-header';
			$msgBodyKey = 'growthexperiments-homepage-discovery-banner-text';
		} else {
			$msgHeaderKey = 'growthexperiments-homepage-discovery-thanks-header';
			$msgBodyKey = 'growthexperiments-homepage-discovery-thanks-text';
		}
		$siteNotice = Html::element( 'span', [ 'class' => 'mw-ge-homepage-discovery-house' ] ) .
			Html::rawElement( 'span', [ 'class' => 'mw-ge-homepage-discovery-text-content' ],
				Html::element( 'h2', [ 'class' => 'mw-ge-homepage-discovery-nojs-message' ],
				$output->msg( $msgHeaderKey )->text() ) .
			Html::rawElement( 'p', [ 'class' => 'mw-ge-homepage-discovery-nojs-banner-text' ],
				$output->msg( $msgBodyKey )
					->params( $username )
					->rawParams( Html::rawElement( 'span', [], new IconWidget( [ 'icon' => 'userAvatar' ] ) ) .
								 Html::element( 'span', [], $username )
					)->parse()
			)
		);
	}

}
