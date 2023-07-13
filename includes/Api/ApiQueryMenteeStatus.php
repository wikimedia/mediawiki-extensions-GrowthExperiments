<?php

namespace GrowthExperiments\Api;

use ApiQuery;
use ApiQueryBase;
use GrowthExperiments\Mentorship\MentorManager;

class ApiQueryMenteeStatus extends ApiQueryBase {

	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		ApiQuery $queryModule,
		$moduleName,
		MentorManager $mentorManager
	) {
		parent::__construct( $queryModule, $moduleName );

		$this->mentorManager = $mentorManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		switch ( $this->mentorManager->getMentorshipStateForUser( $this->getUser() ) ) {
			case MentorManager::MENTORSHIP_ENABLED:
				$statusHumanReadable = 'enabled';
				break;
			case MentorManager::MENTORSHIP_DISABLED:
				$statusHumanReadable = 'disabled';
				break;
			case MentorManager::MENTORSHIP_OPTED_OUT:
				$statusHumanReadable = 'optout';
				break;
			default:
				$statusHumanReadable = 'unknown';
				break;
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'menteestatus' => $statusHumanReadable
		] );
	}
}
