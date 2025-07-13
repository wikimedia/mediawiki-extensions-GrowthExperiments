<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MessageLocalizer;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\IDatabase;

class ReassignMentees {
	use LoggerAwareTrait;

	private IDatabase $dbw;
	private IMentorManager $mentorManager;
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
	 * @param IMentorManager $mentorManager
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
		IMentorManager $mentorManager,
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
	 * Schedule a new job to reassign mentees
	 *
	 * @internal Only public to be used from ReassignMenteesJob
	 * @param string $reassignMessageKey
	 * @param mixed ...$reassignMessageAdditionalParams
	 */
	public function scheduleReassignMenteesJob(
		string $reassignMessageKey,
	   ...$reassignMessageAdditionalParams
	) {
		$jobQueueGroup = $this->jobQueueGroupFactory->makeJobQueueGroup();
		$jobQueue = $jobQueueGroup->get( ReassignMenteesJob::JOB_NAME );

		$jobParams = [
			'mentorId' => $this->mentor->getId(),
			'performerId' => $this->performer->getId(),
			'reassignMessageKey' => $reassignMessageKey,
			'reassignMessageAdditionalParams' => $reassignMessageAdditionalParams,
		];

		// Opportunistically delay the job by a minute, as a lock handoff
		// might be happening (T376124).
		if ( $jobQueue->delayedJobsEnabled() ) {
			$jobParams['jobReleaseTimestamp'] = (int)wfTimestamp() + ExpirationAwareness::TTL_MINUTE;
		} else {
			$this->logger->debug(
				'ReassignMentees failed to delay reassignMenteesJob, delays are not supported'
			);
		}

		$jobQueueGroup->lazyPush(
			new JobSpecification( ReassignMenteesJob::JOB_NAME, $jobParams )
		);
	}

	/**
	 * Reassign mentees currently assigned to the mentor via a job
	 *
	 * If no job is needed, use doReassignMentees directly.
	 *
	 * @param string $reassignMessageKey Message key used in ChangeMentor notification; needs
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
			$this->scheduleReassignMenteesJob(
				$reassignMessageKey,
				...$reassignMessageAdditionalParams
			);
		}
	}

	/**
	 * Actually reassign all mentees currently assigned to the mentor
	 *
	 * @param int|null $limit Maximum number of mentees processed (null means no limit; if used,
	 * caller is responsible for checking if there are any mentees left)
	 * @param string $reassignMessageKey Message key used in in ChangeMentor notification; needs
	 * to accept one parameter (username of the previous mentor). Additional parameters can be
	 * passed via $reassignMessageAdditionalParams.
	 * @param mixed ...$reassignMessageAdditionalParams
	 * @return bool True if successful, false otherwise.
	 */
	public function doReassignMentees(
		?int $limit,
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
		// MentorManager::getMentorForUser and replaced with a valid mentor if needed
		$mentees = $this->mentorStore->getMenteesByMentor( $this->mentor, MentorStore::ROLE_PRIMARY );
		$this->logger->info( __METHOD__ . ' processing {mentees} mentees', [
			'mentees' => count( $mentees ),
		] );
		$numberOfProcessedMentees = 0;
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
			} catch ( WikiConfigException ) {
				$this->logger->warning(
					'ReassignMentees failed to reassign mentees for {mentor}; mentor list is invalid',
					[
						'mentor' => $this->mentor->getName(),
					]
				);
				return false;
			}

			if ( !$newMentor ) {
				$this->logger->warning(
					'ReassignMentees failed to reassign mentees for {mentor}; no mentor is available',
					[
						'mentor' => $this->mentor->getName(),
						'impact' => 'Mentor-mentee relationship dropped',
					]
				);
				$this->mentorStore->dropMenteeRelationship( $mentee );
				continue;
			}

			// ChangeMentor takes care of appropriately logging the outcome
			$changeMentor->execute(
				$newMentor,
				$this->messageLocalizer->msg(
					$reassignMessageKey,
					$this->mentor->getName(),
					...$reassignMessageAdditionalParams
				)->text(),
				true
			);

			$numberOfProcessedMentees += 1;
			if ( $limit && $numberOfProcessedMentees >= $limit ) {
				$this->logger->info( 'ReassignMentees processed the maximum number of mentees', [
					'limit' => $limit,
					'mentor' => $this->mentor->getName(),
				] );
				break;
			}
		}

		if ( !$this->dbw->unlock( $lockName, __METHOD__ ) ) {
			$this->logger->error( 'ReassignMentees failed to release its lock', [
				'mentor' => $this->mentor->getName(),
			] );
		}
		return true;
	}
}
