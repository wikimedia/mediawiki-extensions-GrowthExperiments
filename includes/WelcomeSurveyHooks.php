<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Helpers\LoginHelper;
use MediaWiki\Specials\Hook\PostLoginRedirectHook;
use MediaWiki\Specials\SpecialCreateAccount;
use MediaWiki\Specials\SpecialUserLogin;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

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
		private readonly TitleFactory $titleFactory,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly WelcomeSurveyFactory $welcomeSurveyFactory,
		private readonly CampaignConfig $campaignConfig,
		private readonly CampaignLoader $campaignLoader,
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

	/**
	 * Check if a given title + query string means some kind of editor is open.
	 */
	private function isEditing( ?Title $title, ?array $query = null ): bool {
		return $title && $title->canExist() && (
			// normal editor, VE with some settings
			( $query['action'] ?? null ) === 'edit'
			// VE
			|| ( $query['veaction'] ?? null ) === 'edit'
			// mobile editor
			|| str_starts_with( $title->getFragment(), '/editor/' )
		);
	}

	/**
	 * True if the user started the registration process while in the middle of editing.
	 * @param string|null $returnTo returnto parameter. Read from URL if omitted.
	 * @param string|string[]|null $returnToQuery returntoquery parameter. Read from URL if omitted.
	 */
	private function userWasEditing( ?string $returnTo = null, string|array|null $returnToQuery = null ): bool {
		$context = RequestContext::getMain();
		$returnTo ??= $context->getRequest()->getText( 'returnto' );
		$returntoTitle = ( $returnTo !== '' ) ? $this->titleFactory->newFromText( $returnTo ) : null;
		if ( $returnToQuery === null ) {
			$returnToQuery = wfCgiToArray( $context->getRequest()->getText( 'returntoquery' ) );
		} elseif ( is_string( $returnToQuery ) ) {
			$returnToQuery = wfCgiToArray( $returnToQuery );
		}
		return $this->isEditing( $returntoTitle, $returnToQuery );
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
			&& $user->isAnon() && $this->userWasEditing()
			&& !Util::isMobile( $context->getSkin() )
			&& $this->shouldShowWelcomeSurvey( $context )
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
				|| $this->isEditing( $out->getTitle(), $out->getRequest()->getQueryValues() )
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
		if ( $autocreated || !$this->shouldShowWelcomeSurvey( $context ) ) {
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
		if ( !$this->shouldShowWelcomeSurvey( $context ) ) {
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return true;
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		if ( $group === false ) {
			$returnToQuery = $this->addAccountJustCreatedToQuery( $returnToQuery );
			return true;
		}

		if ( $this->userWasEditing( $returnTo, $returnToQuery ) ) {
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
			|| !$this->shouldShowWelcomeSurvey( $context )
		) {
			return true;
		}

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );

		if ( $this->userWasEditing( $returnTo, $returnToQuery ) ) {
			return true;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;

		$returnTo = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )->getPrefixedText();
		$returnToQuery = $welcomeSurvey->getRedirectUrlQuery( $group, $oldReturnTo, wfArrayToCgi( $oldReturnToQuery ) );
		$type = 'successredirect';
		return false;
	}

	private function shouldShowWelcomeSurvey( IContextSource $context ): bool {
		$loginHelper = new LoginHelper( $context );
		return $this->isWelcomeSurveyEnabled()
			&& !$context->getUser()->isTemp()
			&& !$this->campaignConfig->shouldSkipWelcomeSurvey( $this->campaignLoader->getCampaign() )
			&& !$loginHelper->isDisplayModePopup();
	}

}
