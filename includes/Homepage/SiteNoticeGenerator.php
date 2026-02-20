<?php

namespace GrowthExperiments\Homepage;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Util;
use MediaWiki\Html\Html;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use OOUI\IconWidget;
use UserOptionsUpdateJob;

class SiteNoticeGenerator {
	private UserOptionsLookup $userOptionsLookup;
	private JobQueueGroup $jobQueueGroup;
	private ?bool $homepageDiscoveryNoticeSeen = null;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		JobQueueGroup $jobQueueGroup
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @param string $name
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @param bool &$minervaEnableSiteNotice Reference to $wgMinervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	public function setNotice( $name, &$siteNotice, Skin $skin, &$minervaEnableSiteNotice ) {
		if ( $skin->getTitle()->isSpecial( 'WelcomeSurvey' ) ) {
			// Don't show any notices on the welcome survey.
			return;
		}
		switch ( $name ) {
			case HomepageHooks::CONFIRMEMAIL_QUERY_PARAM:
				if ( $skin->getTitle()->isSpecial( 'Homepage' ) ) {
					return $this->setConfirmEmailSiteNotice( $siteNotice, $skin, $minervaEnableSiteNotice );
				}
				break;
			case 'specialwelcomesurvey':
				if ( $skin->getTitle()->isSpecial( 'Homepage' ) ) {
					return $this->setDiscoverySiteNotice( $siteNotice, $skin, $name, $minervaEnableSiteNotice );
				}
				break;
			case 'welcomesurvey-originalcontext':
				if ( !$skin->getTitle()->isSpecial( 'Homepage' ) ) {
					return $this->setDiscoverySiteNotice( $siteNotice, $skin, $name, $minervaEnableSiteNotice );
				}
				break;
			default:
				return $this->maybeShowIfUserAbandonedWelcomeSurvey(
					$siteNotice,
					$skin,
					$minervaEnableSiteNotice );
		}
	}

	/**
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @param bool &$minervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	private function maybeShowIfUserAbandonedWelcomeSurvey(
		&$siteNotice, Skin $skin, &$minervaEnableSiteNotice
	) {
		if ( $this->isWelcomeSurveyInReferer( $skin )
			|| ( Util::isMobile( $skin ) && !$this->checkAndMarkMobileDiscoveryNoticeSeen( $skin ) )
		) {
			return $this->setDiscoverySiteNotice(
				$siteNotice,
				$skin,
				$skin->getTitle()->isSpecial( 'Homepage' )
					? 'specialwelcomesurvey'
					: 'welcomesurvey-originalcontext',
				$minervaEnableSiteNotice
			);
		}
	}

	private function isWelcomeSurveyInReferer( Skin $skin ): bool {
		$referer = $skin->getRequest()->getHeader( 'REFERER' );
		foreach ( $skin->getLanguage()->getSpecialPageAliases()['WelcomeSurvey'] as $alias ) {
			if ( str_contains( $referer, $alias ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @param bool &$minervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	private function setConfirmEmailSiteNotice(
		&$siteNotice, Skin $skin, &$minervaEnableSiteNotice
	) {
		$output = $skin->getOutput();
		$output->addJsConfigVars( 'shouldShowConfirmEmailNotice', true );
		$output->addModuleStyles( 'ext.growthExperiments.Homepage.styles' );
		$baseCssClassName = 'mw-ge-homepage-confirmemail-nojs';
		$cssClasses = [
			$baseCssClassName,
			// The following classes are generated here:
			// * mw-ge-homepage-confirmemail-nojs-mobile
			// * mw-ge-homepage-confirmemail-nojs-desktop
			Util::isMobile( $skin ) ? $baseCssClassName . '-mobile' : $baseCssClassName . '-desktop',
		];
		$siteNotice = Html::rawElement( 'div', [ 'class' => $cssClasses ],
			new IconWidget( [ 'icon' => 'check', 'flags' => 'success' ] ) . ' ' .
			Html::element( 'span', [ 'class' => 'mw-ge-homepage-confirmemail-nojs-message' ],
				$output->msg( 'confirmemail_loggedin' )->text() )
		);
		$minervaEnableSiteNotice = true;
		// Only triggered for a specific source query parameter, which the user should see only
		// once, so it's OK to suppress all other banners.
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @param string $contextName
	 * @param bool &$minervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	private function setDiscoverySiteNotice(
		&$siteNotice, Skin $skin, $contextName, &$minervaEnableSiteNotice
	) {
		if ( Util::isMobile( $skin ) ) {
			$this->setMobileDiscoverySiteNotice( $siteNotice, $skin, $contextName,
				$minervaEnableSiteNotice );
			$this->checkAndMarkMobileDiscoveryNoticeSeen( $skin );
		} else {
			$this->setDesktopDiscoverySiteNotice( $siteNotice, $skin, $contextName );
		}
		// Only triggered for a specific source query parameter, which the user should see only
		// once, so it's OK to suppress all other banners.
		return false;
	}

	/**
	 * Check and set seen flag for the mobile homapage discovery sitenotice.
	 * (Desktop uses a different mechanism based on guided tours, which has its own seen logic.)
	 * @param Skin $skin
	 * @return bool True if the user has seen the notice already.
	 */
	private function checkAndMarkMobileDiscoveryNoticeSeen( Skin $skin ) {
		// Make multiple calls to this method within the same request a no-op.
		// Note this would be necessary even if we only called it once, because
		// Minerva calls sitenotice hooks multiple times.
		if ( $this->homepageDiscoveryNoticeSeen !== null ) {
			return $this->homepageDiscoveryNoticeSeen;
		}

		$user = $skin->getUser();
		if ( $this->userOptionsLookup->getOption( $user, HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN ) ) {
			$this->homepageDiscoveryNoticeSeen = true;
			return true;
		}

		$this->jobQueueGroup->lazyPush( new UserOptionsUpdateJob( [
			'userId' => $user->getId(),
			'options' => [ HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN => 1 ],
		] ) );
		$this->homepageDiscoveryNoticeSeen = false;
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @param string $contextName
	 */
	private function setDesktopDiscoverySiteNotice(
		&$siteNotice, Skin $skin, $contextName
	) {
		// No-JS banner (hidden from CSS when there's JS support). The JS version is in
		// tours/homepageDiscovery.js.

		$output = $skin->getOutput();
		$output->enableOOUI();
		$output->addModuleStyles( [
			'oojs-ui.styles.icons-user',
			'ext.growthExperiments.HomepageDiscovery.styles',
		] );

		$username = $skin->getUser()->getName();
		if ( $contextName === 'specialwelcomesurvey' ) {
			$msgHeaderKey = 'growthexperiments-homepage-discovery-banner-header';
			$msgBodyKey = 'growthexperiments-homepage-discovery-banner-text';
		} else {
			$msgHeaderKey = 'growthexperiments-homepage-discovery-thanks-header';
			$msgBodyKey = 'growthexperiments-homepage-discovery-thanks-text';
		}
		$siteNotice = Html::rawElement( 'div', [ 'class' => 'mw-ge-homepage-discovery-banner-nojs' ],
			Html::element( 'span', [ 'class' => 'mw-ge-homepage-discovery-house' ] ) .
			Html::rawElement( 'span', [ 'class' => 'mw-ge-homepage-discovery-text-content' ],
				Html::element( 'h2', [ 'class' => 'mw-ge-homepage-discovery-nojs-message' ],
					$output->msg( $msgHeaderKey, $username )->text() ) .
				$this->getDiscoveryTextWithAvatarIcon(
					$output,
					$skin->getUser(),
					$msgBodyKey,
					'mw-ge-homepage-discovery-nojs-banner-text'
				)
			)
		);
	}

	/**
	 * @param string &$siteNotice
	 * @param Skin $skin
	 * @param string $contextName
	 * @param bool &$minervaEnableSiteNotice
	 */
	private function setMobileDiscoverySiteNotice(
		&$siteNotice, Skin $skin, $contextName, &$minervaEnableSiteNotice
	) {
		$output = $skin->getOutput();
		$output->enableOOUI();
		$output->addModuleStyles( [
			'oojs-ui.styles.icons-user',
			'ext.growthExperiments.HomepageDiscovery.styles',
		] );
		$output->addModules( 'ext.growthExperiments.HomepageDiscovery' );

		$user = $skin->getUser();
		$location = ( $contextName === 'specialwelcomesurvey' ) ? 'homepage' : 'nonhomepage';
		$msgHeaderKey = "growthexperiments-homepage-discovery-mobile-$location-banner-header";
		$msgBodyKey = "growthexperiments-homepage-discovery-mobile-$location-banner-text";

		$siteNotice = Html::rawElement( 'div', [ 'class' => 'mw-ge-homepage-discovery-banner-mobile' ],
			Html::element( 'div', [ 'class' => 'mw-ge-homepage-discovery-arrow' ] ) .
			Html::rawElement( 'div', [ 'class' => 'mw-ge-homepage-discovery-message' ],
				$this->getHeader( $output, $user, $msgHeaderKey, $location ) .
				$this->getDiscoveryTextWithAvatarIcon( $output, $user, $msgBodyKey )
			) . new IconWidget( [ 'icon' => 'close',
				'classes' => [ 'mw-ge-homepage-discovery-banner-close' ] ] )
		);

		$minervaEnableSiteNotice = true;
	}

	/**
	 * Get the header (H2) element for the site notice.
	 *
	 * If the user is on the homepage, no header is shown.
	 *
	 * @param OutputPage $output
	 * @param UserIdentity $user
	 * @param string $msgHeaderKey
	 * @param string $location
	 * @return string
	 */
	private function getHeader(
		OutputPage $output,
		UserIdentity $user,
		string $msgHeaderKey,
		string $location
	): string {
		if ( $location === 'homepage' ) {
			return '';
		}
		return Html::element( 'h2', [],
			$output->msg( $msgHeaderKey, $user->getName() )->text()
		);
	}

	private function getDiscoveryTextWithAvatarIcon(
		OutputPage $output, UserIdentity $user, string $msgBodyKey, string $class = ''
	): string {
		return Html::rawElement( 'p', [ 'class' => $class ],
			$output->msg( $msgBodyKey )
				->params( $user->getName() )
				->rawParams(
					new IconWidget( [ 'icon' => 'userAvatar' ] ) .
					// add a word joiner to make the icon stick to the name
					\UtfNormal\Utils::codepointToUtf8( 0x2060 ) .
					Html::element( 'span', [], $user->getName() )
				)->parse()
		);
	}

}
