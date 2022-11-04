<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;

class ReassignMenteesFactory {
	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var MentorStore */
	private $mentorStore;

	/** @var ChangeMentorFactory */
	private $changeMentorFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	/**
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param PermissionManager $permissionManager
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 */
	public function __construct(
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore,
		ChangeMentorFactory $changeMentorFactory,
		PermissionManager $permissionManager,
		JobQueueGroupFactory $jobQueueGroupFactory
	) {
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->permissionManager = $permissionManager;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	/**
	 * @param UserIdentity $mentor
	 * @param IContextSource $context
	 * @return ReassignMentees
	 */
	public function newReassignMentees(
		UserIdentity $mentor,
		IContextSource $context
	): ReassignMentees {
		$reassignMentees = new ReassignMentees(
			$this->mentorManager,
			$this->mentorProvider,
			$this->mentorStore,
			$this->changeMentorFactory,
			$this->permissionManager,
			$this->jobQueueGroupFactory,
			$mentor,
			$context
		);
		$reassignMentees->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $reassignMentees;
	}
}
