<?php

declare( strict_types = 1 );

namespace GrowthExperiments\AccountSetup;

use GrowthExperiments\FeatureManager;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthPostLoginRedirectHook;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
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
	PostLoginRedirectHook
{

	public const string INTEREST_ARTICLES_PROP = 'growthexperiments-interest-articles-editing';

	/**
	 * One of the following values:
	 * reading, editing, both, skipped
	 */
	public const string ACCOUNT_SETUP_MOTIVATION_PROP = 'growthexperiments-account-setup-motivation';

	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly FeatureManager $featureManager,
		private readonly TitleFactory $titleFactory,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly RedirectLookup $redirectLookup,
	) {
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

	/**
	 * True if the user started the registration process while in the middle of editing.
	 * @param string $returnTo
	 * @param string[] $returnToQuery
	 */
	private function userWasEditing( string $returnTo, array $returnToQuery ): bool {
		$returntoTitle = ( $returnTo !== '' ) ? $this->titleFactory->newFromText( $returnTo ) : null;
		return $this->isEditing( $returntoTitle, $returnToQuery );
	}

	/**
	 * Check if a given title + query string means some kind of editor is open.
	 */
	private function isEditing( ?Title $title, array $query ): bool {
		return $title && $title->canExist() && (
				// normal editor, VE with some settings
				( $query['action'] ?? null ) === 'edit'
				// VE
				|| ( $query['veaction'] ?? null ) === 'edit'
				// mobile editor
				|| str_starts_with( $title->getFragment(), '/editor/' )
			);
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ): bool {
		if ( $user->isTemp() || $autocreated ) {
			return true;
		}

		if ( !$this->featureManager->isEarlyOnboardingExperimentTreatment( $user, true ) ) {
			return true;
		}

		$returnTo = RequestContext::getMain()->getRequest()->getText( 'returnto' );
		$this->saveOriginArticleAsInterest( $user, $returnTo );
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

		$newReturnToQuery = $this->maybeRedirectToHomepage( $returnTo, $originalReturnToQuery );
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

		$newReturnToQuery = $this->maybeRedirectToHomepage( $returnTo, $returnToQuery );

		if ( $newReturnToQuery !== null ) {
			$returnToQuery = $newReturnToQuery;
		}
		if ( $originalReturnTo !== $returnTo ) {
			$type = 'successredirect';
		}

		return true;
	}

	/**
	 * This method is intended to adjust both its parameters if the user should be redirected to the Homepage.
	 * But since the two hooks handle returnToQuery differently, its new value is returned and handled by the caller.
	 */
	private function maybeRedirectToHomepage( string &$returnTo, array $returnToQuery ): ?array {
		$user = RequestContext::getMain()->getUser();
		if ( !$this->featureManager->isEarlyOnboardingExperimentTreatment( $user ) ) {
			return null;
		}

		if ( $this->userWasEditing( $returnTo, $returnToQuery ) ) {
			return null;
		}

		$homepageLinkText = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
		if ( $homepageLinkText === null ) {
			return null;
		}

		$returnTo = $homepageLinkText;
		// TODO: figure out what we want/need to do with existing values of $returnToQuery
		return $returnToQuery;
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
