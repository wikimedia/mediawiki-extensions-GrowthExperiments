<?php

namespace GrowthExperiments\PeriodicMetrics;

use GrowthExperiments\Mentorship\Provider\MentorProvider;

class AutoAssignedMentorsMetric implements IMetric {

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param MentorProvider $mentorProvider
	 */
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
	 */
	public function getStatsdKey(): string {
		return 'GrowthExperiments.Mentorship.AutoMentors';
	}
}
