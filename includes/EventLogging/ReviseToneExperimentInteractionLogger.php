<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\ExperimentTestKitchenManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;

class ReviseToneExperimentInteractionLogger {
	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/product_metrics/web/base/1.5.0';
	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.product_metrics.contributors.experiments';

	public function __construct(
		private readonly AbstractExperimentManager $experimentUserManager,
		private readonly ?MetricsClientFactory $metricsClientFactory,
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
		$eventData = [
				'experiment' => $experimentConfig,
		] + $interactionData;
		$this->submitInteraction( RequestContext::getMain(), $action, $eventData );
	}

	/**
	 * Emit an interaction event for the Revise tone experiment to the Test Kitchen instrument.
	 * @param IContextSource $context
	 * @param string $action
	 * @param array $interactionData Interaction data for the event
	 */
	private function submitInteraction(
		IContextSource $context,
		string $action,
		array $interactionData
	): void {
		$client = $this->metricsClientFactory->newMetricsClient( $context );
		$client->submitInteraction(
			self::STREAM,
			self::SCHEMA_VERSIONED,
			$action,
			$interactionData
		);
	}
}
