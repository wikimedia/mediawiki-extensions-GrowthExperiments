<?php

namespace GrowthExperiments\Mentorship\Store;

use LogicException;
use MediaWiki\JobQueue\Job;
use MediaWiki\User\UserIdentityLookup;

/**
 * Job to change mentor in GET context from DatabaseMentorStore
 *
 * The following job parameters are required:
 * 	- menteeId: user ID of the mentee
 * 	- mentorId: user ID of the mentor
 * 	- roleId: ROLE_* constant from MentorStore
 */
class SetUserMentorDatabaseJob extends Job {

	public const JOB_NAME = 'setUserMentorDatabaseJob';
	private UserIdentityLookup $userIdentityLookup;
	private DatabaseMentorStore $databaseMentorStore;

	public function __construct(
		array $params,
		UserIdentityLookup $userIdentityLookup,
		DatabaseMentorStore $databaseMentorStore
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->removeDuplicates = true;

		$this->userIdentityLookup = $userIdentityLookup;
		$this->databaseMentorStore = $databaseMentorStore;
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
		$menteeUser = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['menteeId'] );
		if ( $menteeUser === null ) {
			throw new LogicException(
				__CLASS__ . ' executed for invalid menteeId (' . $this->params['menteeId'] . ')'
			);
		}
		$this->databaseMentorStore->setMentorForUser(
			$menteeUser,
			$this->params['mentorId'] ? $this->userIdentityLookup->getUserIdentityByUserId(
				$this->params['mentorId']
			) : null,
			$this->params['roleId']
		);
		return true;
	}
}
