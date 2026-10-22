<?php

declare( strict_types = 1 );

namespace GrowthExperiments\AccountSetup;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\IExperimentManager;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthPostLoginRedirectHook;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Html\Html;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Hook\SkinAfterBottomScriptsHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Hook\PostLoginRedirectHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;

class AccountSetupHooks implements
	GetPreferencesHook,
	LocalUserCreatedHook,
	SkinAfterBottomScriptsHook,
	PostLoginRedirectHook
{

	public const string INTEREST_ARTICLES_PROP = 'growthexperiments-interest-articles-editing';

	/**
	 * One of the values in ACCOUNT_SETUP_MOTIVATIONS.
	 */
	public const string ACCOUNT_SETUP_MOTIVATION_PROP = 'growthexperiments-account-setup-motivation';

	/** Valid values of the ACCOUNT_SETUP_MOTIVATION_PROP preference. */
	public const array ACCOUNT_SETUP_MOTIVATIONS = [ 'reading', 'editing', 'both', 'skipped' ];

	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly FeatureManager $featureManager,
		private readonly TitleFactory $titleFactory,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly RedirectLookup $redirectLookup,
		private readonly PostSignupOnboardingEligibility $onboardingEligibility,
		private readonly ?ExperimentManagerInterface $experimentManager = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinAfterBottomScripts( $skin, &$text ): true {
		$title = $skin->getTitle();
		$user = $skin->getUser();
		if ( !$user->isNamed() ) {
			return true;
		}
		if ( $title->isSpecial( 'AccountSetup' ) ) {
			$text .= Html::rawElement( 'div', [ 'id' => 'growthexperiments-initial-backdrop' ] );
			return true;
		}
		if ( !$title->isSpecial( 'Homepage' ) ) {
			return true;
		}
		if ( !$this->featureManager->isEarlyOnboardingExperimentTreatment( $user ) ) {
			return true;
		}
		if ( $this->userOptionsManager->getOption( $user, self::ACCOUNT_SETUP_MOTIVATION_PROP ) !== null ) {
			return true;
		}
		$text .= Html::rawElement( 'div', [ 'id' => 'growthexperiments-initial-backdrop' ] );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ): void {
		$preferences[self::INTEREST_ARTICLES_PROP] = [
			'type' => 'api',
		];
		$preferences[self::ACCOUNT_SETUP_MOTIVATION_PROP] = [
			'type' => 'api',
		];
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ): bool {
		if ( $user->isTemp() || $autocreated ) {
			return true;
		}

		if (
			!$this->featureManager->isHomeAvailable() &&
			!$this->featureManager->isEarlyOnboardingExperimentTreatment( $user, true )
		) {
			return true;
		}

		$returnTo = RequestContext::getMain()->getRequest()->getText( 'returnto' );
		$this->saveOriginArticleAsInterest( $user, $returnTo );
		$this->saveSuggestedEditsActivatedFlag( $user );
		return true;
	}

	/**
	 * @see CentralAuthPostLoginRedirectHook::onCentralAuthPostLoginRedirect
	 */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo, string &$returnToQuery, bool $stickHTTPS, string $type, string &$injectedHtml
	): bool {
		if ( $type !== 'signup' || $this->userIdentityUtils->isTemp( RequestContext::getMain()->getUser() ) ) {
			return true;
		}

		$originalReturnToQuery = wfCgiToArray( $returnToQuery );

		$newReturnToQuery = $this->maybeRedirectToOnboarding( $returnTo, $originalReturnToQuery );
		if ( $newReturnToQuery !== null ) {
			$returnToQuery = wfArrayToCgi( $newReturnToQuery );
		}

		return true;
	}

	/** @inheritDoc */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ): bool {
		if ( $type !== 'signup' ||
			$this->extensionRegistry->isLoaded( 'CentralAuth' ) ||
			$this->userIdentityUtils->isTemp( RequestContext::getMain()->getUser() )
		) {
			return true;
		}

		$originalReturnTo = $returnTo;

		$newReturnToQuery = $this->maybeRedirectToOnboarding( $returnTo, $returnToQuery );

		if ( $newReturnToQuery !== null ) {
			$returnToQuery = $newReturnToQuery;
		}
		if ( $originalReturnTo !== $returnTo ) {
			$type = 'successredirect';
		}

		return true;
	}

	/**
	 * This method is intended to adjust both its parameters if the user should be redirected to the account setup.
	 * That is Special:AccountSetup on wikis with Home, and Special:Homepage for the experiment treatment group.
	 * But since the two hooks handle returnToQuery differently, its new value is returned and handled by the caller.
	 */
	private function maybeRedirectToOnboarding( string &$returnTo, array $returnToQuery ): ?array {
		$context = RequestContext::getMain();
		if ( $this->featureManager->isHomeAvailable() ) {
			if ( $this->onboardingEligibility->userWasEditing( $returnTo, $returnToQuery ) ) {
				return null;
			}
			$returnTo = $this->specialPageFactory->getTitleForAlias( 'AccountSetup' )->getPrefixedText();
			return $returnToQuery;
		}

		$user = $context->getUser();
		$isTreatment = $this->featureManager->isEarlyOnboardingExperimentTreatment( $user, true );
		$isControl = $this->featureManager->isEarlyOnboardingExperimentControl( $user, true );
		$shouldStartOnboarding = $this->onboardingEligibility->shouldStartOnboarding(
			$context,
			$returnTo,
			$returnToQuery
		);
		if ( $shouldStartOnboarding && ( $isTreatment || $isControl ) ) {
			$this->experimentManager
				?->getExperiment( IExperimentManager::DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AB_TEST )
				->sendExposure();
		}

		if ( !$isTreatment ) {
			return null;
		}

		if ( $this->onboardingEligibility->userWasEditing( $returnTo, $returnToQuery ) ) {
			return null;
		}

		$homepageLinkText = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
		if ( $homepageLinkText === null ) {
			return null;
		}

		$returnTo = $homepageLinkText;
		if ( $context->getRequest()->getRawVal( 'mpo' ) ) {
			$returnToQuery['mpo'] = $context->getRequest()->getRawVal( 'mpo' );
		}

		// TODO: figure out what we want/need to do with existing values of $returnToQuery
		return $returnToQuery;
	}

	/**
	 * Mark suggested edits as activated for users on wikis with Home, and for users in the early
	 * onboarding experiment treatment group.
	 *
	 * Those users are onboarded through the account setup flow, so they never get the StartEditing
	 * module, which is what normally sets this flag. Without it the suggested edits module would
	 * stay in its uninitiated state (hidden on desktop) and their task edits would not be tagged.
	 *
	 * @see \GrowthExperiments\Specials\SpecialHomepage::getModules()
	 */
	private function saveSuggestedEditsActivatedFlag( UserIdentity $user ): void {
		$this->userOptionsManager->setOption( $user, SuggestedEdits::ACTIVATED_PREF, 1 );
	}

	private function saveOriginArticleAsInterest( UserIdentity $user, string $returnTo ): void {
		$initialInterestTitle = $this->getSignupTitleForInterest( $returnTo );
		if ( $initialInterestTitle ) {
			$interestArticlesToStore = json_encode(
				[ $initialInterestTitle->getPrefixedText() ],
				JSON_THROW_ON_ERROR
			);
			$this->userOptionsManager->setOption(
				$user,
				self::INTEREST_ARTICLES_PROP,
				$interestArticlesToStore
			);
		}
	}

	private function getSignupTitleForInterest( string $returnTo ): ?Title {
		$title = $this->titleFactory->newFromText( $returnTo );
		if ( $title === null ) {
			return null;
		}
		if ( !$title->canExist() ) {
			return null;
		}
		if ( !$title->isContentPage() ) {
			return null;
		}
		$redirectTitle = $this->redirectLookup->getRedirectTarget( $title );
		if ( $redirectTitle ) {
			$title = $this->titleFactory->newFromLinkTarget( $redirectTitle );
		}
		if ( !$title->exists() ) {
			return null;
		}
		if ( !$title->inNamespace( NS_MAIN ) ) {
			return null;
		}
		if ( $title->isMainPage() ) {
			return null;
		}
		return $title;
	}
}
