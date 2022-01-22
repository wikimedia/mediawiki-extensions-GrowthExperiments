<?php

namespace GrowthExperiments\Api;

use ApiQuery;
use ApiQueryBase;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;

class ApiQueryMentorStatus extends ApiQueryBase {

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/**
	 * @param ApiQuery $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		ApiQuery $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if (
			!$this->getConfig()->get( 'GEMentorDashboardEnabled' ) ||
			!$this->mentorProvider->isMentor( $this->getUser() )
		) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'mentorstatus' => $this->mentorStatusManager->getMentorStatus( $this->getUser() )
		] );
	}
}
