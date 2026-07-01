<?php

namespace GrowthExperiments;

use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;

class ExperimentTestKitchenManager implements IExperimentManager {

	public function __construct(
		private readonly ExperimentManager $experimentManager,
	) {
	}

	public function getAssignedGroup( string $experimentName ): ?string {
		return $this->experimentManager->getExperiment( $experimentName )->getAssignedGroup();
	}
}
