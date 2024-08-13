<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\PeriodicMetrics\MetricsFactory;
use GrowthExperiments\Util;
use InvalidArgumentException;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateMetrics extends Maintenance {

	/** @var StatsdDataFactoryInterface */
	private $statsDataFactory;

	/** @var MetricsFactory */
	private $metricsFactory;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription( 'Push calculated metrics to StatsD' );
		$this->addOption( 'verbose', 'Output values of metrics calculated' );
	}

	/**
	 * Init all services
	 */
	private function initServices(): void {
		$services = $this->getServiceContainer();

		$this->statsDataFactory = $services->getPerDbNameStatsdDataFactory();
		$this->metricsFactory = GrowthExperimentsServices::wrap( $services )
			->getMetricsFactory();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		foreach ( MetricsFactory::METRICS as $metricName ) {
			try {
				$metric = $this->metricsFactory->newMetric( $metricName );
			} catch ( InvalidArgumentException $e ) {
				$this->error( 'ERROR: Metric "' . $metricName . '" failed to be constructed' );
				Util::logException( $e );
				continue;
			}

			$metricValue = $metric->calculate();
			$this->statsDataFactory->gauge(
				$metric->getStatsdKey(),
				$metricValue
			);

			if ( $this->hasOption( 'verbose' ) ) {
				$this->output( $metricName . ' is ' . $metricValue . '.' . PHP_EOL );
			}
		}
	}
}

$maintClass = UpdateMetrics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
