<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use Config;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\Hook\BeforeWelcomeCreationHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
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

	/** @var WelcomeSurveyFactory */
	private $welcomeSurveyFactory;

	/**
	 * @param Config $config
	 * @param WelcomeSurveyFactory $welcomeSurveyFactory
	 */
	public function __construct( Config $config, WelcomeSurveyFactory $welcomeSurveyFactory ) {
		$this->config = $config;
		$this->welcomeSurveyFactory = $welcomeSurveyFactory;
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
			$preferences['welcomesurvey-responses'] = [
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
		if ( !$this->isWelcomeSurveyEnabled() ||
			VariantHooks::isGrowthDonorCampaign( RequestContext::getMain() ) ||
			HomepageHooks::getGrowthFeaturesOptInOptOutOverride() === HomepageHooks::GROWTH_FORCE_OPTOUT
		) {
			return;
		}

		$context = RequestContext::getMain();
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
