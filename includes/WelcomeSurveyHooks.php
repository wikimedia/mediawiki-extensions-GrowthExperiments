<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace GrowthExperiments;

use GrowthExperiments\EventLogging\WelcomeSurveyLogger;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\Specials\SpecialWelcomeSurvey;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\SpecialCreateAccount;
use MediaWiki\Specials\SpecialUserLogin;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;

class WelcomeSurveyHooks implements
	GetPreferencesHook,
	LocalUserCreatedHook,
	PostLoginRedirectHook,
	SpecialPage_initListHook,
	SpecialPageBeforeExecuteHook,
	BeforePageDisplayHook
{

	private TitleFactory $titleFactory;
	private SpecialPageFactory $specialPageFactory;
	private WelcomeSurveyFactory $welcomeSurveyFactory;
	private CampaignConfig $campaignConfig;

	public function __construct(
		TitleFactory $titleFactory,
		SpecialPageFactory $specialPageFactory,
		WelcomeSurveyFactory $welcomeSurveyFactory,
		CampaignConfig $campaignConfig
	) {
		$this->titleFactory = $titleFactory;
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
		$list[ 'WelcomeSurvey' ] = function () {
			return new SpecialWelcomeSurvey(
				$this->specialPageFactory,
				$this->welcomeSurveyFactory,
				new WelcomeSurveyLogger(
					LoggerFactory::getInstance( 'GrowthExperiments' )
				)
			);
		};
	}

	/**
	 * Register preference to save the Welcome survey responses.
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[WelcomeSurvey::SURVEY_PROP] = [
			'type' => 'api',
		];
	}

	/**
	 * Check if a given title + query string means some kind of editor is open.
	 * @param Title|null $title
	 * @param array|null $query
	 * @return bool
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
	 * @return bool
	 */
	private function userWasEditing( ?string $returnTo = null, $returnToQuery = null ): bool {
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
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
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
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $user->isTemp() ) {
			return;
		}
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

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		if ( $group === false ) {
			return;
		}

		if ( $this->userWasEditing( $returnTo, $returnToQuery ) ) {
			return;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;
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

		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $context );
		$group = $welcomeSurvey->getGroup();
		$welcomeSurvey->saveGroup( $group );

		if ( $this->userWasEditing( $returnTo, $returnToQuery ) ) {
			return;
		}

		$oldReturnTo = $returnTo;
		$oldReturnToQuery = $returnToQuery;

		$returnTo = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )->getPrefixedText();
		$returnToQuery = $welcomeSurvey->getRedirectUrlQuery( $group, $oldReturnTo, wfArrayToCgi( $oldReturnToQuery ) );
		$type = 'successredirect';
		return false;
	}

	private function shouldShowWelcomeSurvey( IContextSource $context ): bool {
		return !$context->getUser()->isTemp()
			&& !VariantHooks::shouldCampaignSkipWelcomeSurvey(
				VariantHooks::getCampaign( $context ), $this->campaignConfig
			);
	}

}
