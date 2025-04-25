<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewUpdateDataForMentorJob;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\JobQueue\JobQueueGroup;

class ApiMentorDashboardUpdateData extends ApiBase {

	private MentorProvider $mentorProvider;
	private JobQueueGroup $jobQueueGroup;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		MentorProvider $mentorProvider,
		JobQueueGroup $jobQueueGroup
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->mentorProvider->isMentor( $this->getUser() ) ) {
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
