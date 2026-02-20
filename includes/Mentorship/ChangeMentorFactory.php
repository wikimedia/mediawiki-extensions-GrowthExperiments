<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;

class ChangeMentorFactory {

	private LoggerInterface $logger;
	private IMentorManager $mentorManager;
	private MentorStore $mentorStore;
	private UserFactory $userFactory;
	private IConnectionProvider $connectionProvider;

	public function __construct(
		LoggerInterface $logger,
		IMentorManager $mentorManager,
		MentorStore $mentorStore,
		UserFactory $userFactory,
		IConnectionProvider $connectionProvider
	) {
		$this->logger = $logger;
		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
		$this->userFactory = $userFactory;
		$this->connectionProvider = $connectionProvider;
	}

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
			$this->connectionProvider
		);
	}
}
