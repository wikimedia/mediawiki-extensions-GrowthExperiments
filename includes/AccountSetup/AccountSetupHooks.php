<?php

declare( strict_types = 1 );

namespace GrowthExperiments\AccountSetup;

use GrowthExperiments\FeatureManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthPostLoginRedirectHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Hook\PostLoginRedirectHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityUtils;

class AccountSetupHooks implements
	GetPreferencesHook,
	PostLoginRedirectHook
{
	public const string INTEREST_ARTICLES_PROP = 'growthexperiments-interest-articles-editing';

	public function __construct(
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly FeatureManager $featureManager,
		private readonly TitleFactory $titleFactory,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserIdentityUtils $userIdentityUtils,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ): void {
		$preferences[self::INTEREST_ARTICLES_PROP] = [
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

}
