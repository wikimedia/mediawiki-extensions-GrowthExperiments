<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentCoordinatorInterface;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\TimestampFormat;

class FeatureManager {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $growthConfig,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly LoggerInterface $logger,
		private readonly ?ExperimentManagerInterface $experimentManager = null,
		private readonly ?ExperimentCoordinatorInterface $experimentCoordinator = null,
	) {
	}

	/**
	 * How does this wiki give Home to its users?
	 *
	 * PersonalDashboard serves Special:Home and is an optional dependency.
	 * Without it, Home is not available.
	 */
	public function getHomeAvailability(): HomeAvailability {
		if ( !$this->extensionRegistry->isLoaded( 'PersonalDashboard' ) ) {
			return HomeAvailability::Unavailable;
		}
		if ( $this->growthConfig->get( 'GEHomeEnabled' ) ) {
			return HomeAvailability::Wikiwide;
		}
		return HomeAvailability::Unavailable;
	}

	/**
	 * Can this user use Home?
	 *
	 * The wiki must have the feature, and the user must have the newcomer
	 * homepage enabled. The pilot cohort comes from the users that have the
	 * homepage, so Home uses the same preference. Revisit this after the pilot.
	 *
	 * The wiki level state is also tested here. Entry points other than
	 * Special:Home ask this question, and they must stay off on a wiki that
	 * does not have the feature.
	 */
	public function isHomeEnabledForUser( UserIdentity $user ): bool {
		return $this->getHomeAvailability() === HomeAvailability::Wikiwide
			&& $this->userOptionsLookup->getBoolOption(
				$user,
				HomepageHooks::HOMEPAGE_PREF_ENABLE
			);
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
	 * @param UserIdentity $userIdentity the current user
	 * @param bool $userCreatedInThisRequest set to true if this is called in onLocalUserCreated or similar
	 *                                       to ensure the ExperimentManager is aware of the user
	 */
	public function isEarlyOnboardingExperimentControl(
		UserIdentity $userIdentity,
		bool $userCreatedInThisRequest = false
	): bool {
		// When the experiment is scaled, no user is in the control group.
		if ( $this->growthConfig->get( 'GEEarlyOnboardingScaled' ) ) {
			return false;
		}

		if ( !$this->experimentManager ) {
			return false;
		}

		if ( !$this->isEarlyOnboardingExperimentEligible( $userIdentity, $userCreatedInThisRequest ) ) {
			return false;
		}

		$experiment = $this->experimentManager->getExperiment(
			IExperimentManager::DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AB_TEST
		);

		return $experiment->isAssignedGroup( IExperimentManager::VARIANT_CONTROL );
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
		// When the experiment is scaled, all users that registered after the start date get the treatment.
		// The experiment assignment is not read, so TestKitchen is not necessary.
		if ( $this->growthConfig->get( 'GEEarlyOnboardingScaled' ) ) {
			return $this->isRegisteredAfterEarlyOnboardingStart( $user, $userCreatedInThisRequest );
		}

		if ( !$this->experimentManager ) {
			return false;
		}

		if ( !$this->isEarlyOnboardingExperimentEligible( $user, $userCreatedInThisRequest ) ) {
			return false;
		}

		$experiment = $this->experimentManager->getExperiment(
			IExperimentManager::DE_1_3_1_SPECIALHOMEPAGE_ONBOARDING_AB_TEST
		);

		return $experiment->isAssignedGroup( IExperimentManager::VARIANT_TREATMENT );
	}

	private function isEarlyOnboardingExperimentEligible(
		UserIdentity $user,
		bool $userCreatedInThisRequest = false
	): bool {
		if ( !$this->experimentManager ) {
			return false;
		}

		if ( !$this->isRegisteredAfterEarlyOnboardingStart( $user, $userCreatedInThisRequest ) ) {
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

		return true;
	}

	private function isRegisteredAfterEarlyOnboardingStart(
		UserIdentity $user,
		bool $userCreatedInThisRequest
	): bool {
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

		$registrationDate = $userCreatedInThisRequest ?
			wfTimestamp( TimestampFormat::MW ) :
			$this->userRegistrationLookup->getFirstRegistration( $user );
		if ( !$registrationDate ) {
			return false;
		}

		return $registrationDate > $experimentStartDate;
	}

}
