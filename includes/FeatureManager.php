<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

class FeatureManager {
	private IExperimentManager $experimentManager;

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $growthConfig
	) {
	}

	public function setExperimentManager( IExperimentManager $experimentManager ): void {
		$this->experimentManager = $experimentManager;
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

	public function isReviseToneTasksTypeEnabled(): bool {
		return $this->isNewcomerTasksAvailable() &&
			// CirrusSearch is not available in patchdemo
			// $extensionRegistry->isLoaded( 'CirrusSearch' ) &&
			$this->extensionRegistry->isLoaded( 'VisualEditor' ) &&
			$this->growthConfig->get( 'GEReviseToneSuggestedEditEnabled' );
	}

	/**
	 * Should TestKitchen extension be used?
	 *
	 * @return bool
	 */
	public function useTestKitchen(): bool {
		return $this->extensionRegistry->isLoaded( 'TestKitchen' );
	}

	public function shouldShowReviseToneTasksForUser( UserIdentity $user ): bool {
		return $this->isReviseToneTasksTypeEnabled() && (
			$this->experimentManager->getAssignedGroup( IExperimentManager::REVISE_TONE_EXPERIMENT ) ===
				IExperimentManager::VARIANT_TREATMENT
			);
	}

	public function shouldShowCreateAccountV1( ?User $user, Skin $skin ): bool {
		$isAnon = $user === null || $user->isAnon();
		$isMobile = Util::isMobile( $skin );
		$isEnWiki = $this->growthConfig->get( 'DBname' ) === 'enwiki';

		return $isAnon && $isMobile && $isEnWiki && $this->experimentManager->getAssignedGroup(
			IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1
		) === IExperimentManager::VARIANT_TREATMENT;
	}
}
