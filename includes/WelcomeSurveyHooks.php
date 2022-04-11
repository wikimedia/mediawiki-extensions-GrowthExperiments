<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use Config;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use IDBAccessObject;
use MediaWiki\Hook\BeforeWelcomeCreationHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserOptionsLookup;
use RequestContext;
use SpecialUserLogin;
use User;

class WelcomeSurveyHooks implements
	BeforeWelcomeCreationHook,
	GetPreferencesHook,
	SpecialPage_initListHook,
	SpecialPageBeforeExecuteHook
{

	/** @var Config */
	private $config;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var WelcomeSurveyFactory */
	private $welcomeSurveyFactory;

	/** @var CampaignConfig */
	private $campaignConfig;

	/**
	 * @param Config $config
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param WelcomeSurveyFactory $welcomeSurveyFactory
	 * @param CampaignConfig $campaignConfig
	 */
	public function __construct(
		Config $config,
		UserOptionsLookup $userOptionsLookup,
		WelcomeSurveyFactory $welcomeSurveyFactory,
		CampaignConfig $campaignConfig
	) {
		$this->config = $config;
		$this->userOptionsLookup = $userOptionsLookup;
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

	/**
	 * Redirect to the Welcome survey after a new account is created.
	 *
	 * @param string &$welcome_creation_msg
	 * @param string &$injected_html
	 */
	public function onBeforeWelcomeCreation( &$welcome_creation_msg, &$injected_html ) {
		$context = RequestContext::getMain();

		if ( !$this->isWelcomeSurveyEnabled() ||
			VariantHooks::isDonorOrGlamCampaign( RequestContext::getMain(), $this->campaignConfig ) ||
			HomepageHooks::getGrowthFeaturesOptInOptOutOverride() === HomepageHooks::GROWTH_FORCE_OPTOUT
		) {
			return;
		}

		$homepageEnabled = $this->userOptionsLookup->getBoolOption(
			$context->getUser(),
			HomepageHooks::HOMEPAGE_PREF_ENABLE,
			// was probably written in the same request
			IDBAccessObject::READ_LATEST
		);
		if ( !$homepageEnabled ) {
			return;
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group  = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );
		$url = $welcomeSurvey->getRedirectUrl( $group );
		if ( $url ) {
			$context->getOutput()->redirect( $url );
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
}
