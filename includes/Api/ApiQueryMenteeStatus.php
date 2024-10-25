<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;

class ApiQueryMenteeStatus extends ApiQueryBase {

	private MentorManager $mentorManager;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
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
