<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\ExperimentTestKitchenManager;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;

class ReviseToneExperimentInteractionLogger {

	private const EXPERIMENT_STREAM = 'mediawiki.product_metrics.contributors.experiments';

	public function __construct(
		private readonly AbstractExperimentManager $experimentUserManager,
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
		$experiment = $this->experimentUserManager->getCurrentExperiment();
		if ( !$experiment ) {
			return;
		}
		$experimentConfig = $experiment->getExperimentConfig();
		if ( !$experimentConfig ) {
			$this->logger->warning( 'Empty Experiment Config for Revise Tone experiment', [
				'exception' => new \RuntimeException,
				'experiment_action' => $action,
				'experiment_interaction_data' => json_encode( $interactionData ),
				'page_dbkey' => $pageDBkeyForLogging,
			] );
			return;
		}
		if ( $experimentConfig[ 'sampling_unit' ] === 'overridden' ) {
			return;
		}

		// TODO: Can be removed after T408186 is done and the stream has been configured in the experiment UI
		$experiment->setStream( self::EXPERIMENT_STREAM );
		$experiment->send(
			$action,
			$interactionData
		);
	}
}
