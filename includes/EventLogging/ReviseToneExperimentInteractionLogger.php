<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\ExperimentTestKitchenManager;
use GrowthExperiments\IExperimentManager;
use MediaWiki\Registration\ExtensionRegistry;

class ReviseToneExperimentInteractionLogger {

	public function __construct(
		private readonly IExperimentManager $experimentUserManager,
	) {
	}

	public function log( string $action, array $interactionData ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) ) {
			return;
		}
		if ( !$this->experimentUserManager instanceof ExperimentTestKitchenManager ) {
			return;
		}
		$experiment = $this->experimentUserManager->getExperiment( IExperimentManager::REVISE_TONE_EXPERIMENT );
		if ( !$experiment ) {
			return;
		}

		$experiment->send(
			$action,
			$interactionData
		);
	}
}
