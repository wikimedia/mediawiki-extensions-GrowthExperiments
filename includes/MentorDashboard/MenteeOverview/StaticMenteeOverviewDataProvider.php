<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use MediaWiki\User\UserIdentity;

class StaticMenteeOverviewDataProvider implements MenteeOverviewDataProvider {
	/** @var array */
	private $mentorData;

	/**
	 * @param array $mentorData Static data to return
	 */
	public function __construct( array $mentorData ) {
		$this->mentorData = $mentorData;
	}

	/**
	 * @inheritDoc
	 */
	public function getFormattedDataForMentor( UserIdentity $mentor ): array {
		return $this->mentorData;
	}
}
