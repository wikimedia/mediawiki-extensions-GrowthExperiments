<?php

namespace GrowthExperiments;

use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\Extension\TestKitchen\Sdk\UnenrolledExperiment;

class ExperimentTestKitchenManager implements IExperimentManager {

	public function __construct(
		private readonly ExperimentManager $experimentManager,
	) {
	}

	public function getExperiment( string $experimentName ): ?Experiment {
		$exp = $this->experimentManager->getExperiment( $experimentName );
		// Prevents attempting to log events for UnenrolledExperiment
		if ( $exp instanceof UnenrolledExperiment ) {
			return null;
		}
		return $exp;
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
