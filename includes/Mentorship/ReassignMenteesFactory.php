<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;

class ReassignMenteesFactory {

	private ILoadBalancer $dbLoadBalancer;
	private MentorManager $mentorManager;
	private MentorProvider $mentorProvider;
	private MentorStore $mentorStore;
	private ChangeMentorFactory $changeMentorFactory;
	private PermissionManager $permissionManager;
	private JobQueueGroupFactory $jobQueueGroupFactory;

	/**
	 * @param ILoadBalancer $dbLoadBalancer
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param PermissionManager $permissionManager
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 */
	public function __construct(
		ILoadBalancer $dbLoadBalancer,
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore,
		ChangeMentorFactory $changeMentorFactory,
		PermissionManager $permissionManager,
		JobQueueGroupFactory $jobQueueGroupFactory
	) {
		$this->dbLoadBalancer = $dbLoadBalancer;
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->permissionManager = $permissionManager;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	/**
	 * @param UserIdentity $performer
	 * @param UserIdentity $mentor
	 * @param IContextSource $context
	 * @return ReassignMentees
	 */
	public function newReassignMentees(
		UserIdentity $performer,
		UserIdentity $mentor,
		IContextSource $context
	): ReassignMentees {
		$reassignMentees = new ReassignMentees(
			$this->dbLoadBalancer->getConnection( DB_PRIMARY ),
			$this->mentorManager,
			$this->mentorProvider,
			$this->mentorStore,
			$this->changeMentorFactory,
			$this->permissionManager,
			$this->jobQueueGroupFactory,
			$performer,
			$mentor,
			$context
		);
		$reassignMentees->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $reassignMentees;
	}
}
