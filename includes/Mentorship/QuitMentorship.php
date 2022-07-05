<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\ScopedCallback;

class QuitMentorship {
	use LoggerAwareTrait;

	public const STAGE_LISTED_AS_MENTOR = 1;
	public const STAGE_NOT_LISTED_HAS_MENTEES = 2;
	public const STAGE_NOT_LISTED_NO_MENTEES = 3;

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

	/** @var UserIdentity */
	private $mentor;

	/** @var IContextSource */
	private $context;

	/**
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param PermissionManager $permissionManager
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param UserIdentity $mentor
	 * @param IContextSource $context
	 */
	public function __construct(
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore,
		ChangeMentorFactory $changeMentorFactory,
		PermissionManager $permissionManager,
		JobQueueGroupFactory $jobQueueGroupFactory,
		UserIdentity $mentor,
		IContextSource $context
	) {
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->permissionManager = $permissionManager;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->mentor = $mentor;
		$this->context = $context;
		$this->logger = new NullLogger();
	}

	/**
	 * @return int One of QuitMentorship::STAGE_* constants
	 */
	public function getStage(): int {
		if ( $this->mentorProvider->isMentor( $this->mentor ) ) {
			return self::STAGE_LISTED_AS_MENTOR;
		} elseif ( $this->mentorStore->hasAnyMentees( $this->mentor, MentorStore::ROLE_PRIMARY ) ) {
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
	 * to accept one parameter (username of the previous mentor).
	 * @return bool True if successful, false otherwise.
	 */
	public function doReassignMentees(
		string $reassignMessageKey
	): bool {
		$guard = $this->permissionManager->addTemporaryUserRights( $this->mentor, 'bot' );

		// only process primary mentors (T309984). Backup mentors will be automatically ignored by
		// MentorPageMentorManager::getMentorForUser and replaced with a valid mentor if needed
		$mentees = $this->mentorStore->getMenteesByMentor( $this->mentor, MentorStore::ROLE_PRIMARY );
		foreach ( $mentees as $mentee ) {
			$changeMentor = $this->changeMentorFactory->newChangeMentor(
				$mentee,
				$this->mentor,
				$this->context
			);

			try {
				$newMentor = $this->mentorManager->getRandomAutoAssignedMentor( $mentee );
			} catch ( WikiConfigException $e ) {
				ScopedCallback::consume( $guard );
				$this->logger->warning(
					'QuitMentorship failed to reassign mentees for {mentor}; mentor list is invalid',
					[
						'mentor' => $this->mentor->getName()
					]
				);
				return false;
			}

			if ( !$newMentor ) {
				$this->logger->warning(
					'QuitMentorship failed to reassign mentees for {mentor}; no mentor is available',
					[
						'mentor' => $this->mentor->getName()
					]
				);
				// this is a continue, because a mentor can be available for other users (for
				// instance, if they're a mentor themselves).
				continue;
			}

			$changeMentor->execute(
				$newMentor,
				$this->context->msg( $reassignMessageKey, $this->mentor->getName() )->text()
			);
		}

		// Revoke temporary bot rights
		ScopedCallback::consume( $guard );
		return true;
	}
}
