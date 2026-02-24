<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILoadBalancer;

class ReassignMenteesFactory {

	public function __construct(
		private LoggerInterface $logger,
		private ILoadBalancer $dbLoadBalancer,
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
		IContextSource $context
	): ReassignMentees {
		$reassignMentees = new ReassignMentees(
			$this->logger,
			$this->dbLoadBalancer->getConnection( DB_PRIMARY ),
			$this->mentorManager,
			$this->mentorStore,
			$this->changeMentorFactory,
			$this->jobQueueGroupFactory,
			$this->userFactory,
			$performer,
			$mentor,
			$context
		);
		return $reassignMentees;
	}
}
