<?php

namespace GrowthExperiments\PeriodicMetrics;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\LoadBalancer;

class MetricsFactory {

	/** @var string[] */
	public const METRICS = [
		AutoAssignedMentorsMetric::class,
		InactiveMentorsMetric::class,
		NewcomersByMentorMetric::class,
	];

	/** @var LoadBalancer */
	private $loadBalancer;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param UserEditTracker $userEditTracker
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param MentorProvider $mentorProvider
	 */
	public function __construct(
		LoadBalancer $loadBalancer,
		UserEditTracker $userEditTracker,
		UserIdentityLookup $userIdentityLookup,
		MentorProvider $mentorProvider
	) {
		$this->loadBalancer = $loadBalancer;
		$this->userEditTracker = $userEditTracker;
		$this->userIdentityLookup = $userIdentityLookup;
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
					$this->userIdentityLookup,
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
