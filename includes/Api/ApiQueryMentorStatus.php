<?php

namespace GrowthExperiments\Api;

use ApiQuery;
use ApiQueryBase;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\MentorManager;

class ApiQueryMentorStatus extends ApiQueryBase {

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/**
	 * @param ApiQuery $mainModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		ApiQuery $mainModule,
		$moduleName,
		MentorManager $mentorManager,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorManager = $mentorManager;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if (
			!$this->getConfig()->get( 'GEMentorDashboardEnabled' ) ||
			!$this->mentorManager->isMentor( $this->getUser() )
		) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'mentorstatus' => $this->mentorStatusManager->getMentorStatus( $this->getUser() )
		] );
	}
}
