<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
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

	public function shouldShowCreateAccountV2( ?User $user, Skin $skin, WebRequest $request ): bool {
		$isAnon = $user === null || $user->isAnon();
		$isMobile = Util::isMobile( $skin );
		$isEnWiki = $this->growthConfig->get( 'DBname' ) === 'enwiki';
		$experimentGroupFromManager = $this->experimentManager->getAssignedGroup(
			IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2
		);
		$experimentGroupFromRequest = $this->getExperimentEnrollmentGroupFromRequest(
			$request,
			IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V2
		);

		$isTreatmentGroup = ( $experimentGroupFromManager === IExperimentManager::VARIANT_TREATMENT ) ||
			( $experimentGroupFromRequest === IExperimentManager::VARIANT_TREATMENT );

		return $isAnon && $isMobile && $isEnWiki && $isTreatmentGroup;
	}

	public function shouldShowCreateAccountNoBenefitsTreatment( ?User $user, Skin $skin, WebRequest $request ): bool {
		$isAnon = $user === null || $user->isAnon();
		$isDesktop = !Util::isMobile( $skin );
		$isEnWiki = $this->growthConfig->get( 'DBname' ) === 'enwiki';
		$experimentGroupFromManager = $this->experimentManager->getAssignedGroup(
			IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP
		);
		$experimentGroupFromRequest = $this->getExperimentEnrollmentGroupFromRequest(
			$request,
			IExperimentManager::CREATE_ACCOUNT_NO_BENEFITS_DESKTOP
		);

		$isTreatmentGroup = ( $experimentGroupFromManager === IExperimentManager::VARIANT_TREATMENT ) ||
			( $experimentGroupFromRequest === IExperimentManager::VARIANT_TREATMENT );

		return $isAnon && $isDesktop && $isEnWiki && $isTreatmentGroup;
	}

	private function getExperimentEnrollmentGroupFromRequest( WebRequest $request, string $experimentName ): string {
		$experimentUrlString = array_find(
			$request->getArray( 'experiments' ) ?? [],
			static fn ( $value ) => str_starts_with( $value, $experimentName ),
		);
		if ( !$experimentUrlString ) {
			return 'unsampled';
		}
		[ , $experimentGroup ] = explode( ':', $experimentUrlString );
		return $experimentGroup;
	}
}
