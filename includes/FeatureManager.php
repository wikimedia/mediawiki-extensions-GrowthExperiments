<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\TimestampFormat;

class FeatureManager {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $growthConfig,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly LoggerInterface $logger,
		private readonly ?ExperimentManagerInterface $experimentManager = null,
	) {
	}

	public function areLinkRecommendationsEnabled(): bool {
		return $this->growthConfig->get( 'GENewcomerTasksLinkRecommendationsEnabled' );
	}

	public function isNewcomerTasksAvailable(): bool {
		return $this->extensionRegistry->isLoaded( 'WikimediaMessages' ) &&
			$this->growthConfig->get( 'GEHomepageSuggestedEditsEnabled' );
	}

	public function isLinkRecommendationsAvailable(): bool {
		return $this->isNewcomerTasksAvailable() &&
			$this->extensionRegistry->isLoaded( 'CirrusSearch' ) &&
			$this->extensionRegistry->isLoaded( 'VisualEditor' ) &&
			$this->growthConfig->get( 'GENewcomerTasksLinkRecommendationsEnabled' );
	}

	public function areImageRecommendationDependenciesSatisfied(): bool {
		return $this->isNewcomerTasksAvailable() &&
			$this->extensionRegistry->isLoaded( 'CirrusSearch' ) &&
			$this->extensionRegistry->isLoaded( 'VisualEditor' );
	}

	/**
	 * @param UserIdentity $user The current user
	 */
	public function isEarlyOnboardingExperimentTreatment( UserIdentity $user ): bool {
		if ( !$this->experimentManager ) {
			return false;
		}
		$configuredExperimentStartDate = $this->growthConfig->get( 'GEAccountSetupExperimentStartRegistrationDate' );
		if ( !$configuredExperimentStartDate ) {
			return false;
		}
		$experimentStartDate = wfTimestamp( TimestampFormat::MW, $configuredExperimentStartDate );
		if ( $experimentStartDate === false ) {
			$this->logger->error(
				'Configured timestamp for GEAccountSetupExperimentStartRegistrationDate is invalid!',
				[
					'exception' => new \RuntimeException,
					'configuredTimestamp' => $configuredExperimentStartDate,
				]
			);
			return false;
		}

		$experiment = $this->experimentManager->getExperiment(
			IExperimentManager::DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AB_TEST
		);
		if ( !$experiment->isAssignedGroup( IExperimentManager::VARIANT_TREATMENT ) ) {
			return false;
		}

		$registrationDate = $this->userRegistrationLookup->getFirstRegistration( $user );
		if ( !$registrationDate ) {
			return false;
		}

		return $registrationDate > $experimentStartDate;
	}

}
