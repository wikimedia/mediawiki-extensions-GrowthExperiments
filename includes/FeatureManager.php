<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentCoordinatorInterface;
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
		private readonly ?ExperimentCoordinatorInterface $experimentCoordinator = null,
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
	 * @param bool $userCreatedInThisRequest set to true if this is called in onLocalUserCreated or similar
	 *                                       to ensure the ExperimentManager is aware of the user
	 */
	public function isEarlyOnboardingExperimentTreatment(
		UserIdentity $user,
		bool $userCreatedInThisRequest = false
	): bool {
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

		if ( $userCreatedInThisRequest ) {
			if ( $this->experimentCoordinator ) {
				$this->experimentCoordinator->updateUser( $user );
			} else {
				$this->logger->error(
					'TestKitchen ExperimentCoordinator missing but experiment user adjustment requested.',
					[
						'exception' => new \RuntimeException,
					]
				);
			}
		}
		$experiment = $this->experimentManager->getExperiment(
			IExperimentManager::DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AB_TEST
		);
		if ( !$experiment->isAssignedGroup( IExperimentManager::VARIANT_TREATMENT ) ) {
			return false;
		}

		$registrationDate = $userCreatedInThisRequest ?
			wfTimestamp( TimestampFormat::MW ) :
			$this->userRegistrationLookup->getFirstRegistration( $user );
		if ( !$registrationDate ) {
			return false;
		}

		return $registrationDate > $experimentStartDate;
	}

}
