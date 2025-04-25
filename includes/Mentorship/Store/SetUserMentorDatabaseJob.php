<?php

namespace GrowthExperiments\Mentorship\Store;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\JobQueue\GenericParameterJob;
use MediaWiki\JobQueue\Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;

/**
 * Job to change mentor in GET context from DatabaseMentorStore
 *
 * The following job parameters are required:
 * 	- menteeId: user ID of the mentee
 * 	- mentorId: user ID of the mentor
 * 	- roleId: ROLE_* constant from MentorStore
 */
class SetUserMentorDatabaseJob extends Job implements GenericParameterJob {

	private UserFactory $userFactory;
	private DatabaseMentorStore $databaseMentorStore;

	/**
	 * @inheritDoc
	 */
	public function __construct( $params = null ) {
		parent::__construct( 'setUserMentorDatabaseJob', $params );
		$this->removeDuplicates = true;

		// Init services
		$services = MediaWikiServices::getInstance();
		$this->userFactory = $services->getUserFactory();
		$this->databaseMentorStore = GrowthExperimentsServices::wrap( $services )
			->getDatabaseMentorStore();
	}

	/**
	 * @inheritDoc
	 */
	public function ignoreDuplicates() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();

		// avoid overriding the stored mentorId for the same menteeId multiple times
		if ( isset( $info['params']['mentorId'] ) ) {
			unset( $info['params']['mentorId'] );
		}

		return $info;
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$this->databaseMentorStore->setMentorForUser(
			$this->userFactory->newFromId( $this->params['menteeId'] ),
			$this->params['mentorId'] ? $this->userFactory->newFromId(
				$this->params['mentorId']
			) : null,
			$this->params['roleId']
		);
		return true;
	}
}
