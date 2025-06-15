<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use MediaWiki\JobQueue\Job;
use MediaWiki\User\UserIdentityLookup;

/**
 * Job to update data shown by the mentor dashboard for a given mentor
 *
 * This job is started by the mentor, if they wish to do an one-off update
 * of the data.
 *
 * The following job parameters are required:
 * 	- mentorId: user ID of the mentor
 */
class MenteeOverviewUpdateDataForMentorJob extends Job {

	public const JOB_NAME = 'menteeOverviewUpdateDataForMentor';
	private UserIdentityLookup $userIdentityLookup;
	private MenteeOverviewDataUpdater $menteeOverviewDataUpdater;

	public function __construct(
		array $params,
		UserIdentityLookup $userIdentityLookup,
		MenteeOverviewDataUpdater $menteeOverviewDataUpdater
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->removeDuplicates = true;

		$this->userIdentityLookup = $userIdentityLookup;
		$this->menteeOverviewDataUpdater = $menteeOverviewDataUpdater;
	}

	/** @inheritDoc */
	public function run() {
		$mentor = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['mentorId'] );
		if ( !$mentor ) {
			// invalid ID passed
			return true;
		}

		$this->menteeOverviewDataUpdater->updateDataForMentor(
			$mentor
		);
		return true;
	}
}
