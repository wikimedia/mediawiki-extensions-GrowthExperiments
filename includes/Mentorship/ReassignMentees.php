<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MessageLocalizer;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IDatabase;

class ReassignMentees {
	use LoggerAwareTrait;

	public const STAGE_LISTED_AS_MENTOR = 1;
	public const STAGE_NOT_LISTED_HAS_MENTEES = 2;
	public const STAGE_NOT_LISTED_NO_MENTEES = 3;

	private IDatabase $dbw;
	private MentorManager $mentorManager;
	private MentorProvider $mentorProvider;
	private MentorStore $mentorStore;
	private ChangeMentorFactory $changeMentorFactory;
	private JobQueueGroupFactory $jobQueueGroupFactory;
	private StatusFormatter $statusFormatter;
	private UserIdentity $performer;
	private UserIdentity $mentor;
	private MessageLocalizer $messageLocalizer;

	/**
	 * @param IDatabase $dbw
	 * @param MentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param StatusFormatter $statusFormatter
	 * @param UserIdentity $performer
	 * @param UserIdentity $mentor
	 * @param MessageLocalizer $messageLocalizer
	 */
	public function __construct(
		IDatabase $dbw,
		MentorManager $mentorManager,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore,
		ChangeMentorFactory $changeMentorFactory,
		JobQueueGroupFactory $jobQueueGroupFactory,
		StatusFormatter $statusFormatter,
		UserIdentity $performer,
		UserIdentity $mentor,
		MessageLocalizer $messageLocalizer
	) {
		$this->dbw = $dbw;
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->statusFormatter = $statusFormatter;
		$this->performer = $performer;
		$this->mentor = $mentor;
		$this->messageLocalizer = $messageLocalizer;
		$this->logger = new NullLogger();
	}

	/**
	 * @return int One of ReassignMentees::STAGE_* constants
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
	 * If no job is needed, use doReassignMentees directly.
	 *
	 * @param string $reassignMessageKey Message key used in in ChangeMentor notification; needs
	 * to accept one parameter (username of the previous mentor). Additional parameters can be
	 * passed via $reassignMessageAdditionalParams.
	 * @param mixed ...$reassignMessageAdditionalParams
	 */
	public function reassignMentees(
		string $reassignMessageKey,
		...$reassignMessageAdditionalParams
	): void {
		// checking if any mentees exist is a cheap operation; do not submit a job if it is going
		// to be a no-op.
		if ( $this->mentorStore->hasAnyMentees( $this->mentor, MentorStore::ROLE_PRIMARY ) ) {
			$this->jobQueueGroupFactory->makeJobQueueGroup()->lazyPush(
				new ReassignMenteesJob( [
					'mentorId' => $this->mentor->getId(),
					'performerId' => $this->performer->getId(),
					'reassignMessageKey' => $reassignMessageKey,
					'reassignMessageAdditionalParams' => $reassignMessageAdditionalParams,
				] )
			);
		}
	}

	/**
	 * Actually reassign all mentees currently assigned to the mentor
	 *
	 * @param string $reassignMessageKey Message key used in in ChangeMentor notification; needs
	 * to accept one parameter (username of the previous mentor). Additional parameters can be
	 * passed via $reassignMessageAdditionalParams.
	 * @param mixed ...$reassignMessageAdditionalParams
	 * @return bool True if successful, false otherwise.
	 */
	public function doReassignMentees(
		string $reassignMessageKey,
		...$reassignMessageAdditionalParams
	): bool {
		$lockName = 'GrowthExperiments-ReassignMentees-' . $this->mentor->getId() .
			WikiMap::getCurrentWikiId();
		if ( !$this->dbw->lock( $lockName, __METHOD__, 0 ) ) {
			$this->logger->warning(
				__METHOD__ . ' failed to acquire a lock for {mentor}', [
					'mentor' => $this->mentor->getName(),
				]
			);
			return false;
		}

		// only process primary mentors (T309984). Backup mentors will be automatically ignored by
		// MentorPageMentorManager::getMentorForUser and replaced with a valid mentor if needed
		$mentees = $this->mentorStore->getMenteesByMentor( $this->mentor, MentorStore::ROLE_PRIMARY );
		$this->logger->info( __METHOD__ . ' processing {mentees} mentees', [
			'mentees' => count( $mentees ),
		] );
		foreach ( $mentees as $mentee ) {
			$this->logger->debug( __METHOD__ . ' processing {mentor}', [
				'mentor' => $mentee->getName(),
			] );
			$changeMentor = $this->changeMentorFactory->newChangeMentor(
				$mentee,
				$this->performer
			);

			try {
				$newMentor = $this->mentorManager->getRandomAutoAssignedMentor( $mentee );
			} catch ( WikiConfigException $e ) {
				$this->logger->warning(
					'ReassignMentees failed to reassign mentees for {mentor}; mentor list is invalid',
					[
						'mentor' => $this->mentor->getName()
					]
				);
				return false;
			}

			if ( !$newMentor ) {
				$this->logger->warning(
					'ReassignMentees failed to reassign mentees for {mentor}; no mentor is available',
					[
						'mentor' => $this->mentor->getName(),
						'impact' => 'Mentor-mentee relationship dropped'
					]
				);
				$this->mentorStore->dropMenteeRelationship( $mentee );
				continue;
			}

			$status = $changeMentor->execute(
				$newMentor,
				$this->messageLocalizer->msg(
					$reassignMessageKey,
					$this->mentor->getName(),
					...$reassignMessageAdditionalParams
				)->text(),
				true
			);
			if ( !$status->isOK() ) {
				$this->logger->warning(
					'ReassignMentees failed to assign {mentor} as {user}\'s mentor for {reason}',
					[
						'mentor' => $newMentor->getName(),
						'user' => $mentee->getName(),
						'reason' => $this->statusFormatter->getWikiText( $status, [ 'lang' => 'en' ] )
					]
				);
			}
		}

		$this->dbw->unlock( $lockName, __METHOD__ );
		return true;
	}
}
