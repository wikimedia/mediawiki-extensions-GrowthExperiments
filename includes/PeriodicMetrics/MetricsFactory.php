<?php

namespace GrowthExperiments\PeriodicMetrics;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use MediaWiki\User\UserEditTracker;
use Wikimedia\Rdbms\ILoadBalancer;

class MetricsFactory {

	public const METRICS = [
		AutoAssignedMentorsMetric::class,
		InactiveMentorsMetric::class,
		NewcomersByMentorMetric::class,
	];

	private ILoadBalancer $loadBalancer;
	private UserEditTracker $userEditTracker;
	private MentorProvider $mentorProvider;

	public function __construct(
		ILoadBalancer $loadBalancer,
		UserEditTracker $userEditTracker,
		MentorProvider $mentorProvider
	) {
		$this->loadBalancer = $loadBalancer;
		$this->userEditTracker = $userEditTracker;
		$this->mentorProvider = $mentorProvider;
	}

	/**
	 * @param string $className
	 * @return IMetric
	 * @throws InvalidArgumentException if metric class name is not supported
	 */
	public function newMetric( string $className ): IMetric {
		switch ( $className ) {
			case AutoAssignedMentorsMetric::class:
				return new AutoAssignedMentorsMetric( $this->mentorProvider );
			case InactiveMentorsMetric::class:
				return new InactiveMentorsMetric(
					$this->userEditTracker,
					$this->mentorProvider
				);
			case NewcomersByMentorMetric::class:
				return new NewcomersByMentorMetric(
					$this,
					$this->loadBalancer->getConnection( DB_REPLICA )
				);
			default:
				throw new InvalidArgumentException( 'Unsupported metric class name' );
		}
	}
}
