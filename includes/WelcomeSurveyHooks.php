<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use Config;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use RequestContext;
use User;

class WelcomeSurveyHooks implements
	\MediaWiki\Hook\BeforeWelcomeCreationHook,
	\MediaWiki\Preferences\Hook\GetPreferencesHook,
	\MediaWiki\SpecialPage\Hook\SpecialPage_initListHook
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
			$list[ 'WelcomeSurvey' ] = [
				'class' => SpecialWelcomeSurvey::class,
				'services' => [ 'GrowthExperimentsWelcomeSurveyFactory' ]
			];
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

}
