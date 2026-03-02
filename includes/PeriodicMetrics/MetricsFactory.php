<?php

namespace GrowthExperiments\PeriodicMetrics;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use MediaWiki\User\UserEditTracker;
use Wikimedia\Rdbms\IConnectionProvider;

class MetricsFactory {

	/** @var class-string<IMetric>[] */
	public const METRICS = [
		AutoAssignedMentorsMetric::class,
		InactiveMentorsMetric::class,
		NewcomersByMentorMetric::class,
	];

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly UserEditTracker $userEditTracker,
		private readonly MentorProvider $mentorProvider,
	) {
	}

	/**
	 * @param class-string<IMetric> $className
	 * @return IMetric
	 * @throws InvalidArgumentException if metric class name is not supported
	 */
	public function newMetric( string $className ): IMetric {
		return match ( $className ) {
			AutoAssignedMentorsMetric::class => new AutoAssignedMentorsMetric(
				$this->mentorProvider
			),
			InactiveMentorsMetric::class => new InactiveMentorsMetric(
				$this->userEditTracker,
				$this->mentorProvider
			),
			NewcomersByMentorMetric::class => new NewcomersByMentorMetric(
				$this,
				$this->dbProvider->getReplicaDatabase()
			),
			default => throw new InvalidArgumentException( 'Unsupported metric class name' )
		};
	}
}
