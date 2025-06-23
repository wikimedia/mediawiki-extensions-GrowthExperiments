<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\PeriodicMetrics\MetricsFactory;
use GrowthExperiments\Util;
use InvalidArgumentException;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Stats\StatsFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateMetrics extends Maintenance {

	/** @var StatsFactory */
	private $statsFactory;

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

		$this->statsFactory = $services->getStatsFactory();
		$this->metricsFactory = GrowthExperimentsServices::wrap( $services )
			->getMetricsFactory();
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();
		$wiki = WikiMap::getCurrentWikiId();

		foreach ( MetricsFactory::METRICS as $metricName ) {
			try {
				$metric = $this->metricsFactory->newMetric( $metricName );
			} catch ( InvalidArgumentException $e ) {
				$this->error( 'ERROR: Metric "' . $metricName . '" failed to be constructed' );
				Util::logException( $e );
				continue;
			}

			$metricValue = $metric->calculate();
			$statsLibKey = $metric->getStatsLibKey();
			$this->statsFactory->withComponent( 'GrowthExperiments' )
				->getGauge( $statsLibKey )
				->setLabel( 'wiki', $wiki )
				->set( $metricValue );

			if ( $this->hasOption( 'verbose' ) ) {
				$this->output( $metricName . ' is ' . $metricValue . '.' . PHP_EOL );
			}
		}
	}
}

$maintClass = UpdateMetrics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
