<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\PeriodicMetrics\MetricsFactory;
use InvalidArgumentException;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * @covers \GrowthExperiments\PeriodicMetrics\MetricsFactory
 */
class MetricsFactoryTest extends MediaWikiUnitTestCase {

	public function testInvalidMetricName() {
		$metricsFactory = new MetricsFactory(
			$this->createNoOpMock( LoadBalancer::class ),
			$this->createNoOpMock( UserEditTracker::class ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( MentorProvider::class )
		);

		$this->expectException( InvalidArgumentException::class );
		$metricsFactory->newMetric( 'nonexistent metric name' );
	}
}
