<?php

namespace GrowthExperiments\LevelingUp;

use MediaWiki\JobQueue\Job;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Stats\StatsFactory;

abstract class AbstractDelayedNotificationJob extends Job {

	private StatsFactory $statsFactory;

	public function __construct(
		string $command, array $params,
		StatsFactory $statsFactory
	) {
		parent::__construct( $command, $params );

		$this->statsFactory = $statsFactory;
	}

	/**
	 * Log the notification delay into StatsFactory
	 *
	 * This method:
	 *      (1) Gets the expected job release timestamp via self::getReleaseTimestamp())
	 *      (2) Compares the current timestamp with the expected one
	 *      (3) Sends the difference to StatsFactory as `notification_delay`
	 *
	 * Caller is expected to call this method right after the notification is actually sent.
	 *
	 * @return void
	 */
	protected function measureNotificationDelay(): void {
		$expectedTimestamp = $this->getReleaseTimestamp();
		if ( $expectedTimestamp === null ) {
			// nothing to log, the job was not delayed
			return;
		}

		$difference = (int)wfTimestamp() - $expectedTimestamp;
		$this->statsFactory
			->withComponent( 'GrowthExperiments' )
			->getGauge( 'notification_delay' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->setLabel( 'notification_type', $this->getType() )
			->set( $difference );
	}
}
