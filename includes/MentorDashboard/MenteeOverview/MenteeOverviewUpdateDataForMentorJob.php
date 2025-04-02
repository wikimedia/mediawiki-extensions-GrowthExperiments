<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\MediaWikiServices;
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
class MenteeOverviewUpdateDataForMentorJob extends Job implements GenericParameterJob {

	/** @var MenteeOverviewDataUpdater */
	private $menteeOverviewDataUpdater;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/**
	 * @inheritDoc
	 */
	public function __construct( $params = null ) {
		parent::__construct( 'menteeOverviewUpdateDataForMentor', $params );
		$this->removeDuplicates = true;

		// Init services
		$services = MediaWikiServices::getInstance();
		$this->menteeOverviewDataUpdater = GrowthExperimentsServices::wrap( $services )
			->getMenteeOverviewDataUpdater();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
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
