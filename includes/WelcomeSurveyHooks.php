<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use Config;
use DerivativeContext;
use ExtensionRegistry;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use IContextSource;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use RequestContext;
use SpecialUserLogin;
use User;

class WelcomeSurveyHooks implements
	GetPreferencesHook,
	LocalUserCreatedHook,
	PostLoginRedirectHook,
	SpecialPage_initListHook,
	SpecialPageBeforeExecuteHook
{

	/** @var Config */
	private $config;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/** @var WelcomeSurveyFactory */
	private $welcomeSurveyFactory;

	/** @var CampaignConfig */
	private $campaignConfig;

	/**
	 * @param Config $config
	 * @param SpecialPageFactory $specialPageFactory
	 * @param WelcomeSurveyFactory $welcomeSurveyFactory
	 * @param CampaignConfig $campaignConfig
	 */
	public function __construct(
		Config $config,
		SpecialPageFactory $specialPageFactory,
		WelcomeSurveyFactory $welcomeSurveyFactory,
		CampaignConfig $campaignConfig
	) {
		$this->config = $config;
		$this->specialPageFactory = $specialPageFactory;
		$this->welcomeSurveyFactory = $welcomeSurveyFactory;
		$this->campaignConfig = $campaignConfig;
	}

	/**
	 * Register WelcomeSurvey special page.
	 *
	 * @param array &$list
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->isWelcomeSurveyEnabled() ) {
			$list[ 'WelcomeSurvey' ] = function () {
				return new SpecialWelcomeSurvey(
					$this->welcomeSurveyFactory,
					new WelcomeSurveyLogger(
						LoggerFactory::getInstance( 'GrowthExperiments' )
					)
				);
			};
		}
	}

	/**
	 * Register preference to save the Welcome survey responses.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		if ( $this->isWelcomeSurveyEnabled() ) {
			$preferences[WelcomeSurvey::SURVEY_PROP] = [
				'type' => 'api',
			];
		}
	}

	private function isWelcomeSurveyEnabled() {
		return $this->config->get( 'WelcomeSurveyEnabled' );
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		$context = RequestContext::getMain();
		$user = $context->getUser();
		if ( $special instanceof SpecialUserLogin && $user->isAnon() ) {
			$request = $context->getRequest();
			if ( $user->isAnon() && $request->getCookie( WelcomeSurveyLogger::INTERACTION_PHASE_COOKIE ) ) {
				$welcomeSurveyLogger = new WelcomeSurveyLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
				$welcomeSurveyLogger->initialize( $request, $user, Util::isMobile( $context->getSkin() ) );
				$welcomeSurveyLogger->logInteraction( WelcomeSurveyLogger::WELCOME_SURVEY_LOGGED_OUT );
			}
		}
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		if ( $autocreated || !$this->shouldShowWelcomeSurvey( $context ) ) {
			return;
		}
		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );
	}

	/** @inheritDoc */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo, string &$returnToQuery, bool $stickHTTPS, string $type, string &$injectedHtml
	) {
		$context = RequestContext::getMain();
		if ( $type !== 'signup'
			|| !$this->shouldShowWelcomeSurvey( $context )
		) {
			return;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;
		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		if ( $group === false ) {
			return;
		}
		$returnToQueryArray = $welcomeSurvey->getRedirectUrlQuery( $group, $oldReturnTo, $oldReturnToQuery );
		if ( $returnToQueryArray === false ) {
			return;
		}

		$returnTo = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )->getPrefixedText();
		$returnToQuery = wfArrayToCgi( $returnToQueryArray );
		$injectedHtml = '';
		return false;
	}

	/** @inheritDoc */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		$context = RequestContext::getMain();
		if ( $type !== 'signup'
			 // handled by onCentralAuthPostLoginRedirect
			|| ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' )
			|| !$this->shouldShowWelcomeSurvey( $context )
		) {
			return;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;
		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group  = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );
		$returnTo = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )->getPrefixedText();
		$returnToQuery = $welcomeSurvey->getRedirectUrlQuery( $group, $oldReturnTo, wfArrayToCgi( $oldReturnToQuery ) );
		$type = 'successredirect';
		return false;
	}

	/**
	 * @param IContextSource $context
	 * @return bool
	 */
	private function shouldShowWelcomeSurvey( IContextSource $context ): bool {
		return $this->isWelcomeSurveyEnabled()
			&& HomepageHooks::getGrowthFeaturesOptInOptOutOverride() !== HomepageHooks::GROWTH_FORCE_OPTOUT
			&& !VariantHooks::shouldCampaignSkipWelcomeSurvey(
				VariantHooks::getCampaign( $context ), $this->campaignConfig
			);
	}

}
