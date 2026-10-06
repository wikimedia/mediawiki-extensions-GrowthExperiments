<?php

declare( strict_types = 1 );

namespace GrowthExperiments\AccountSetup;

use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Specials\Helpers\LoginHelper;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

/**
 * Decides if a new account gets the post-signup onboarding flow.
 *
 * The Welcome Survey and the Account Setup flow both use these checks.
 */
class PostSignupOnboardingEligibility {

	public function __construct(
		private readonly Config $config,
		private readonly TitleFactory $titleFactory,
		private readonly CampaignConfig $campaignConfig,
		private readonly CampaignLoader $campaignLoader,
	) {
	}

	/**
	 * True if the context of the signup allows the onboarding flow.
	 *
	 * This does not check if the user was editing before the signup.
	 */
	public function canShowOnboarding( IContextSource $context ): bool {
		$loginHelper = new LoginHelper( $context );
		return $this->config->get( 'WelcomeSurveyEnabled' )
			&& !$context->getUser()->isTemp()
			&& !$this->campaignConfig->shouldSkipWelcomeSurvey( $this->campaignLoader->getCampaign() )
			&& !$loginHelper->isDisplayModePopup();
	}

	/**
	 * True if the user started the registration process while in the middle of editing.
	 *
	 * @param string $returnTo
	 * @param string[] $returnToQuery
	 */
	public function userWasEditing( string $returnTo, array $returnToQuery ): bool {
		$returntoTitle = ( $returnTo !== '' ) ? $this->titleFactory->newFromText( $returnTo ) : null;
		return $this->isEditing( $returntoTitle, $returnToQuery );
	}

	/**
	 * Check if a given title + query string means some kind of editor is open.
	 */
	public function isEditing( ?Title $title, array $query ): bool {
		return $title && $title->canExist() && (
			// normal editor, VE with some settings
			( $query['action'] ?? null ) === 'edit'
			// VE
			|| ( $query['veaction'] ?? null ) === 'edit'
			// mobile editor
			|| str_starts_with( $title->getFragment(), '/editor/' )
		);
	}
}
