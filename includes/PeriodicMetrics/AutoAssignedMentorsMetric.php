<?php

namespace GrowthExperiments\PeriodicMetrics;

use GrowthExperiments\Mentorship\Provider\MentorProvider;

class AutoAssignedMentorsMetric implements IMetric {

	/** @var MentorProvider */
	private $mentorProvider;

	public function __construct(
		MentorProvider $mentorProvider
	) {
		$this->mentorProvider = $mentorProvider;
	}

	/**
	 * @inheritDoc
	 */
	public function calculate(): int {
		return count( $this->mentorProvider->getAutoAssignedMentors() );
	}

	/**
	 * @inheritDoc
	 * @deprecated Will be removed when StatsD support is dropped. Use getStatsLibKey() instead.
	 */
	public function getStatsdKey(): string {
		return 'GrowthExperiments.Mentorship.AutoMentors';
	}

	/**
	 * @inheritDoc
	 */
	public function getStatsLibKey(): string {
		return 'mentorship_automentors';
	}
}
