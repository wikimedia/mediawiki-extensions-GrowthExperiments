<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\ExperimentTestKitchenManager;
use GrowthExperiments\IExperimentManager;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;

class ReviseToneExperimentInteractionLogger {

	private const EXPERIMENT_STREAM = 'mediawiki.product_metrics.contributors.experiments';

	public function __construct(
		private readonly IExperimentManager $experimentUserManager,
		private readonly LoggerInterface $logger,
	) {
	}

	public function log( string $action, array $interactionData, ?string $pageDBkeyForLogging = null ): void {
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
