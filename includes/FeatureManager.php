<?php

namespace GrowthExperiments;

use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;

class FeatureManager {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $growthConfig
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
		return $this->extensionRegistry->isLoaded( 'TestKitchen' )
			&& $this->growthConfig->get( 'GEUseTestKitchenExtension' );
	}
}
