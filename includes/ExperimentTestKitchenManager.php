<?php

namespace GrowthExperiments;

use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;

class ExperimentTestKitchenManager implements IExperimentManager {

	public function __construct(
		private readonly ExperimentManager $experimentManager,
	) {
	}

	public function getExperiment( string $experimentName ): ?Experiment {
		return $this->experimentManager->getExperiment( $experimentName );
	}

	public function getAssignments(): array {
		$variants = [];
		foreach ( static::EXPERIMENTS as $experimentName ) {
			$experiment = $this->experimentManager->getExperiment( $experimentName );
			$variants[ $experimentName ] = $experiment->getAssignedGroup();
		}
		return $variants;
	}

	public function getAssignedGroup( string $experimentName ): ?string {
		return $this->experimentManager->getExperiment( $experimentName )->getAssignedGroup();
	}
}
