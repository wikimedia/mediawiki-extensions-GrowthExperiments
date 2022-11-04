<?php

namespace GrowthExperiments\PeriodicMetrics;

use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\IDatabase;

class NewcomersByMentorMetric implements IMetric {

	/** @var MetricsFactory */
	private $metricsFactory;

	/** @var IDatabase */
	private $dbr;

	/**
	 * @param MetricsFactory $metricsFactory
	 * @param IDatabase $dbr
	 */
	public function __construct(
		MetricsFactory $metricsFactory,
		IDatabase $dbr
	) {
		$this->metricsFactory = $metricsFactory;
		$this->dbr = $dbr;
	}

	/**
	 * @inheritDoc
	 */
	public function calculate(): int {
		$autoMentors = $this->metricsFactory
			->newMetric( AutoAssignedMentorsMetric::class )
			->calculate();

		if ( $autoMentors === 0 ) {
			// prevent a "Divison by zero" PHP Warning coming from $recentAccounts / $autoMentors
			// below
			return 0;
		}

		$recentAccounts = $this->dbr->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_type' => 'newusers',
				'log_action' => 'create',
				'log_timestamp > ' . $this->dbr->timestamp(
					(int)wfTimestamp() - ExpirationAwareness::TTL_MONTH
				)
			] )
			->caller( __METHOD__ )
			->fetchField();

		return (int)round(
			$recentAccounts / $autoMentors
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getStatsdKey(): string {
		return 'GrowthExperiments.Mentorship.NewcomerByMentors';
	}
}
