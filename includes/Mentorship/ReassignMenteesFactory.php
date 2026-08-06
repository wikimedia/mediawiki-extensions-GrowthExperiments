<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\LockManager\ILockManager;

class ReassignMenteesFactory {

	public function __construct(
		private LoggerInterface $logger,
		private IMentorManager $mentorManager,
		private MentorStore $mentorStore,
		private ChangeMentorFactory $changeMentorFactory,
		private JobQueueGroupFactory $jobQueueGroupFactory,
		private UserFactory $userFactory,
		private ILockManager $lockManager
	) {
	}

	public function newReassignMentees(
		UserIdentity $performer,
		UserIdentity $mentor,
		MessageLocalizer $messageLocalizer
	): ReassignMentees {
		$reassignMentees = new ReassignMentees(
			$this->logger,
			$this->mentorManager,
			$this->mentorStore,
			$this->changeMentorFactory,
			$this->jobQueueGroupFactory,
			$this->userFactory,
			$this->lockManager,
			$performer,
			$mentor,
			$messageLocalizer
		);
		return $reassignMentees;
	}
}
