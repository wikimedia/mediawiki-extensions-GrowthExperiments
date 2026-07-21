<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\GrowthConnectionProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class ReassignMenteesFactory {

	public function __construct(
		private LoggerInterface $logger,
		private GrowthConnectionProvider $growthConnectionProvider,
		private IMentorManager $mentorManager,
		private MentorStore $mentorStore,
		private ChangeMentorFactory $changeMentorFactory,
		private JobQueueGroupFactory $jobQueueGroupFactory,
		private UserFactory $userFactory
	) {
	}

	public function newReassignMentees(
		UserIdentity $performer,
		UserIdentity $mentor,
		MessageLocalizer $messageLocalizer
	): ReassignMentees {
		$reassignMentees = new ReassignMentees(
			$this->logger,
			$this->growthConnectionProvider->getPrimaryDatabase(),
			$this->mentorManager,
			$this->mentorStore,
			$this->changeMentorFactory,
			$this->jobQueueGroupFactory,
			$this->userFactory,
			$performer,
			$mentor,
			$messageLocalizer
		);
		return $reassignMentees;
	}
}
