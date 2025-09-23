<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;

class ApiQueryMentorStatus extends ApiQueryBase {

	private MentorProvider $mentorProvider;
	private MentorStatusManager $mentorStatusManager;

	public function __construct(
		ApiQuery $mainModule,
		string $moduleName,
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
		if ( !$this->mentorProvider->isMentor( $this->getUser() ) ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'mentorstatus' => $this->mentorStatusManager->getMentorStatus( $this->getUser() ),
		] );
	}
}
