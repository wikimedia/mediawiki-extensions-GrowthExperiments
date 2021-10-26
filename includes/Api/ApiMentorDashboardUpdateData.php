<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewUpdateDataForMentorJob;
use GrowthExperiments\Mentorship\MentorManager;
use JobQueueGroup;

class ApiMentorDashboardUpdateData extends ApiBase {

	/** @var MentorManager */
	private $mentorManager;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		JobQueueGroup $jobQueueGroup
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if (
			!$this->getConfig()->get( 'GEMentorDashboardBackendEnabled' ) ||
			!$this->mentorManager->isMentor( $this->getUser() )
		) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		if ( $this->getUser()->pingLimiter( 'growthmentordashboardupdatedata' ) ) {
			$this->dieWithError( [ 'actionthrottledtext' ] );
		}

		$this->jobQueueGroup->lazyPush( new MenteeOverviewUpdateDataForMentorJob( [
			'mentorId' => $this->getUser()->getId()
		] ) );

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}
}
