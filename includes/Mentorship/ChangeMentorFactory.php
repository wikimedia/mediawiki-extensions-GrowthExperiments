<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IReadableDatabase;

class ChangeMentorFactory {

	private LoggerInterface $logger;
	private MentorManager $mentorManager;
	private MentorStore $mentorStore;
	private UserFactory $userFactory;
	private IReadableDatabase $dbr;

	/**
	 * @param LoggerInterface $logger
	 * @param MentorManager $mentorManager
	 * @param MentorStore $mentorStore
	 * @param UserFactory $userFactory
	 * @param IReadableDatabase $dbr
	 */
	public function __construct(
		LoggerInterface $logger,
		MentorManager $mentorManager,
		MentorStore $mentorStore,
		UserFactory $userFactory,
		IReadableDatabase $dbr
	) {
		$this->logger = $logger;
		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
		$this->userFactory = $userFactory;
		$this->dbr = $dbr;
	}

	/**
	 * @param UserIdentity $mentee
	 * @param UserIdentity $performer
	 * @return ChangeMentor
	 */
	public function newChangeMentor(
		UserIdentity $mentee,
		UserIdentity $performer
	): ChangeMentor {
		return new ChangeMentor(
			$mentee,
			$performer,
			$this->logger,
			$this->mentorManager->getMentorForUserIfExists( $mentee ),
			$this->mentorManager,
			$this->mentorStore,
			$this->userFactory,
			$this->dbr
		);
	}
}
