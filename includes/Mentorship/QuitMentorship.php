<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\ScopedCallback;

class QuitMentorship {

	public const STAGE_LISTED_AS_MENTOR = 1;
	public const STAGE_NOT_LISTED_HAS_MENTEES = 2;
	public const STAGE_NOT_LISTED_NO_MENTEES = 3;

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStore */
	private $mentorStore;

	/** @var ChangeMentorFactory */
	private $changeMentorFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	/** @var UserIdentity */
	private $mentor;

	/** @var IContextSource */
	private $context;

	/**
	 * @param MentorManager $mentorManager
	 * @param MentorStore $mentorStore
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param PermissionManager $permissionManager
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param UserIdentity $mentor
	 * @param IContextSource $context
	 */
	public function __construct(
		MentorManager $mentorManager,
		MentorStore $mentorStore,
		ChangeMentorFactory $changeMentorFactory,
		PermissionManager $permissionManager,
		JobQueueGroupFactory $jobQueueGroupFactory,
		UserIdentity $mentor,
		IContextSource $context
	) {
		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->permissionManager = $permissionManager;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->mentor = $mentor;
		$this->context = $context;
	}

	/**
	 * @return int One of QuitMentorship::STAGE_* constants
	 */
	public function getStage(): int {
		if ( $this->mentorManager->isMentor( $this->mentor ) ) {
			return self::STAGE_LISTED_AS_MENTOR;
		} elseif ( $this->mentorStore->getMenteesByMentor( $this->mentor ) !== [] ) {
			return self::STAGE_NOT_LISTED_HAS_MENTEES;
		} else {
			return self::STAGE_NOT_LISTED_NO_MENTEES;
		}
	}

	/**
	 * Reassign mentees currently assigned to the mentor via a job
	 *
	 * If reassigning can happen without a job, you can use
	 * doReassignMentees directly.
	 *
	 * @param string $reassignMessageKey
	 */
	public function reassignMentees( string $reassignMessageKey ): void {
		$this->jobQueueGroupFactory->makeJobQueueGroup()->lazyPush(
			new ReassignMenteesJob( [
				'mentorId' => $this->mentor->getId(),
				'reassignMessageKey' => $reassignMessageKey
			] )
		);
	}

	/**
	 * Actually reassign all mentees currently assigned to the mentor
	 *
	 * @param string $reassignMessageKey Message key used in in ChangeMentor notification; needs
	 * to accept one parameter (username of the new mentor).
	 */
	public function doReassignMentees(
		string $reassignMessageKey
	): void {
		$guard = $this->permissionManager->addTemporaryUserRights( $this->mentor, 'bot' );

		$mentees = $this->mentorStore->getMenteesByMentor( $this->mentor );
		foreach ( $mentees as $mentee ) {
			$changeMentor = $this->changeMentorFactory->newChangeMentor(
				$mentee,
				$this->mentor,
				$this->context
			);

			$newMentor = $this->mentorManager->getRandomAutoAssignedMentor( $mentee );
			$changeMentor->execute(
				$newMentor,
				wfMessage( $reassignMessageKey, $newMentor->getName() )->text()
			);
		}

		// Revoke temporary bot rights
		ScopedCallback::consume( $guard );
	}
}
