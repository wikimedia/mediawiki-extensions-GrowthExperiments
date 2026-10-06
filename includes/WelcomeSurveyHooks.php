<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

use GrowthExperiments\AccountSetup\PostSignupOnboardingEligibility;
use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Hook\PostLoginRedirectHook;
use MediaWiki\Specials\SpecialCreateAccount;
use MediaWiki\Specials\SpecialUserLogin;

class WelcomeSurveyHooks implements
	GetPreferencesHook,
	LocalUserCreatedHook,
	PostLoginRedirectHook,
	SpecialPage_initListHook,
	SpecialPageBeforeExecuteHook,
	BeforePageDisplayHook
{

	public function __construct(
		private readonly Config $config,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly WelcomeSurveyFactory $welcomeSurveyFactory,
		private readonly CampaignConfig $campaignConfig,
		private readonly CampaignLoader $campaignLoader,
		private readonly FeatureManager $featureManager,
		private readonly PostSignupOnboardingEligibility $onboardingEligibility,
		private readonly ?ExperimentManager $experimentManager,
	) {
	}

	/**
	 * Register WelcomeSurvey special page.
	 *
	 * @inheritDoc
	 */
	public function onSpecialPage_initList( &$list ): bool {
		if ( $this->isWelcomeSurveyEnabled() ) {
			$list[ 'WelcomeSurvey' ] = function () {
				return new SpecialWelcomeSurvey(
					$this->specialPageFactory,
					$this->welcomeSurveyFactory,
					new WelcomeSurveyLogger(
						LoggerFactory::getInstance( 'GrowthExperiments' )
					),
					$this->featureManager,
					$this->experimentManager,
				);
			};
		}
		return true;
	}

	/**
	 * Register preference to save the Welcome survey responses.
	 *
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ): bool {
		if ( $this->isWelcomeSurveyEnabled() ) {
			$preferences[WelcomeSurvey::SURVEY_PROP] = [
				'type' => 'api',
			];
		}
		return true;
	}

	private function isWelcomeSurveyEnabled(): bool {
		return $this->config->get( 'WelcomeSurveyEnabled' );
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ): bool {
		$context = $special->getContext();
		$user = $context->getUser();
		if ( $special instanceof SpecialUserLogin && $user->isAnon() ) {
			$request = $context->getRequest();
			if ( $user->isAnon() && $request->getCookie( WelcomeSurveyLogger::INTERACTION_PHASE_COOKIE ) ) {
				$welcomeSurveyLogger = new WelcomeSurveyLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
				$welcomeSurveyLogger->initialize( $request, $user, Util::isMobile( $context->getSkin() ) );
				$welcomeSurveyLogger->logInteraction( WelcomeSurveyLogger::WELCOME_SURVEY_LOGGED_OUT );
			}
		} elseif (
			$special instanceof SpecialCreateAccount
			&& $user->isAnon()
			&& $this->onboardingEligibility->userWasEditing(
				$context->getRequest()->getText( 'returnto' ),
				wfCgiToArray( $context->getRequest()->getText( 'returntoquery' ) )
			)
			&& !Util::isMobile( $context->getSkin() )
			&& $this->onboardingEligibility->canShowOnboarding( $context )
		) {
			$context->getOutput()->addModules( 'ext.growthExperiments.MidEditSignup' );
			$context->getOutput()->addJsConfigVars( 'wgGEMidEditSignup', true );
		}
		return true;
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getRequest()->getCookie( 'ge.midEditSignup' )
			&& !Util::isMobile( $skin )
			// maybe the user filled out or dismissed the survey in another tab, don't show then
			&& $this->welcomeSurveyFactory->newWelcomeSurvey( $out->getContext() )->isUnfinished()
			&& (
				// Check if we are post-edit, somewhat relying on \MediaWiki\EditPage\EditPage internals.
				// There isn't a good way to do that; between trying to check the dynamically named
				// postedit cookie and looking for the JS variable Article::show() sets based on
				// that cookie, this is the less painful one.
				( $out->getJsConfigVars()['wgPostEdit'] ?? false )
				// Also load the module if the editor is open, as some editors save without
				// reloading the page.
				|| $this->onboardingEligibility->isEditing( $out->getTitle(), $out->getRequest()->getQueryValues() )
			)
		) {
			$out->addModules( 'ext.growthExperiments.MidEditSignup' );
		}
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ): bool {
		if ( $user->isTemp() ) {
			return true;
		}
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		if ( $autocreated || !$this->onboardingEligibility->canShowOnboarding( $context ) ) {
			return true;
		}
		if ( $this->featureManager->isEarlyOnboardingExperimentTreatment( $context->getUser(), true ) ) {
			return true;
		}
		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );
		return true;
	}

	private function addAccountJustCreatedToQuery( string $query ): string {
		$asArray = wfCgiToArray( $query );
		$asArray['accountJustCreated'] = 1;
		return wfArrayToCgi( $asArray );
	}

	/** @inheritDoc */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo, string &$returnToQuery, bool $stickHTTPS, string $type, string &$injectedHtml
	): bool {
		if ( $type !== 'signup' ) {
			return true;
		}

		$campaign = $this->campaignLoader->getCampaign();
		if ( $this->campaignConfig->isGrowthCampaign( $campaign )
			&& $this->campaignConfig->shouldSkipWelcomeSurvey( $campaign )
			&& !$returnTo
		) {
			$returnTo = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return false;
		}

		$context = RequestContext::getMain();
		if ( !$this->onboardingEligibility->canShowOnboarding( $context ) ) {
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return true;
		}
		if ( $this->featureManager->isEarlyOnboardingExperimentTreatment( $context->getUser(), true ) ) {
			return true;
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		if ( $group === false ) {
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return true;
		}

		if ( $this->onboardingEligibility->userWasEditing( $returnTo, wfCgiToArray( $returnToQuery ) ) ) {
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return true;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;
		if ( str_contains( $oldReturnToQuery, 'accountJustCreated' ) ) {
			$asArray = wfCgiToArray( $oldReturnToQuery );
			unset( $asArray['accountJustCreated'] );
			$oldReturnToQuery = wfArrayToCgi( $asArray );
		}
		$returnToQueryArray = $welcomeSurvey->getRedirectUrlQuery( $group, $oldReturnTo, $oldReturnToQuery );
		if ( $returnToQueryArray === false ) {
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return true;
		}
		// Ensure accountJustCreated query param is added directly to the URL instead of to the returntoquery param
		// on WS redirections
		$returnToQueryArray += [
			'accountJustCreated' => '1',
		];

		$returnTo = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )->getPrefixedText();
		$returnToQuery = wfArrayToCgi( $returnToQueryArray );
		$injectedHtml = '';
		return false;
	}

	/** @inheritDoc */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ): bool {
		$context = RequestContext::getMain();
		if ( $type !== 'signup'
			 // handled by onCentralAuthPostLoginRedirect
			|| ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' )
			|| !$this->onboardingEligibility->canShowOnboarding( $context )
		) {
			return true;
		}
		if ( $this->featureManager->isEarlyOnboardingExperimentTreatment( $context->getUser() ) ) {
			return true;
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );

		if ( $this->onboardingEligibility->userWasEditing( $returnTo, $returnToQuery ) ) {
			return true;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;

		$returnTo = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )->getPrefixedText();
		$returnToQuery = $welcomeSurvey->getRedirectUrlQuery( $group, $oldReturnTo, wfArrayToCgi( $oldReturnToQuery ) );
		$type = 'successredirect';
		return false;
	}

}
