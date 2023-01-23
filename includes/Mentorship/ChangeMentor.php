<?php

namespace GrowthExperiments\Mentorship;

use DeferredUpdates;
use EchoEvent;
use GrowthExperiments\Mentorship\Store\MentorStore;
use LogPager;
use ManualLogEntry;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Status;

class ChangeMentor {
	private UserIdentity $mentee;
	private ?UserIdentity $mentor;
	private ?UserIdentity $newMentor;
	private UserIdentity $performer;
	private LoggerInterface $logger;
	private LogPager $logPager;
	private MentorManager $mentorManager;
	private MentorStore $mentorStore;
	private UserFactory $userFactory;

	/**
	 * @param UserIdentity $mentee Mentee's user object
	 * @param UserIdentity $performer Performer's user object
	 * @param LoggerInterface $logger
	 * @param Mentor|null $mentor Old mentor
	 * @param LogPager $logPager
	 * @param MentorManager $mentorManager
	 * @param MentorStore $mentorStore
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		UserIdentity $mentee,
		UserIdentity $performer,
		LoggerInterface $logger,
		?Mentor $mentor,
		LogPager $logPager,
		MentorManager $mentorManager,
		MentorStore $mentorStore,
		UserFactory $userFactory
	) {
		$this->logger = $logger;

		$this->performer = $performer;
		$this->mentee = $mentee;
		$this->logPager = $logPager;
		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
		$this->userFactory = $userFactory;
		$this->mentor = $mentor ? $mentor->getUserIdentity() : null;
	}

	/**
	 * Was mentee's mentor already changed?
	 *
	 * @return bool
	 */
	public function wasMentorChanged(): bool {
		$this->logPager->doQuery();
		return (bool)$this->logPager->getResult()->fetchRow();
	}

	/**
	 * Log mentor change
	 *
	 * @param string $reason Reason for the change
	 */
	protected function log( string $reason ) {
		$this->logger->debug(
			'Logging mentor change for {mentee} from {oldMentor} to {newMentor} by {performer}', [
				'mentee' => $this->mentee,
				'oldMentor' => $this->mentor,
				'newMentor' => $this->newMentor,
				'performer' => $this->performer,
		] );

		if ( $this->performer->getId() === $this->newMentor->getId() ) {
			$primaryLogtype = 'claimmentee';
		} else {
			$primaryLogtype = 'setmentor';
		}
		$logEntry = new ManualLogEntry(
			'growthexperiments',
			$this->mentor ?
				$primaryLogtype :
				"$primaryLogtype-no-previous-mentor"
		);
		$logEntry->setPerformer( $this->performer );
		$logEntry->setTarget(
			$this->userFactory->newFromUserIdentity( $this->mentee )
				->getUserPage()
		);
		$logEntry->setComment( $reason );
		$parameters = [];
		if ( $this->mentor ) {
			// $this->mentor is null when no mentor existed previously
			$parameters['4::previous-mentor'] = $this->mentor->getName();
		}
		if ( $this->performer->getId() !== $this->newMentor->getId() ) {
			$parameters['5::new-mentor'] = $this->newMentor->getName();
		}
		$logEntry->setParameters( $parameters );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}

	/**
	 * Verify the mentor change is possible
	 *
	 * @return Status
	 */
	private function validate(): Status {
		$this->logger->debug(
			'Validating mentor change for {mentee} from {oldMentor} to {newMentor}', [
				'mentee' => $this->mentee,
				'oldMentor' => $this->mentor,
				'newMentor' => $this->newMentor
		] );
		$status = Status::newGood();

		if ( !$this->mentee->isRegistered() ) {
			$this->logger->info(
				'Mentor change for {mentee} from {oldMentor} to {newMentor}'
				. ' did not succeed, because the mentee doesn\'t exist', [
					'mentee' => $this->mentee,
					'oldMentor' => $this->mentor,
					'newMentor' => $this->newMentor
			] );
			$status->fatal( 'growthexperiments-homepage-claimmentee-no-user' );
			return $status;
		}
		if ( $this->mentor && $this->mentor->equals( $this->newMentor ) ) {
			$this->logger->info(
				'Mentor change for {mentee} from {oldMentor} to {newMentor}'
				. ' did not succeed, because the old and new mentor are equal', [
					'mentee' => $this->mentee,
					'oldMentor' => $this->mentor,
					'newMentor' => $this->newMentor
			] );
			$status->fatal(
				'growthexperiments-homepage-claimmentee-already-mentor',
				$this->mentee->getName(),
				$this->performer->getName()
			);
			return $status;
		}
		return $status;
	}

	/**
	 * Notify mentee about the mentor change
	 *
	 * @param string $reason Reason for the change
	 */
	protected function notify( string $reason ) {
		if ( \ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			DeferredUpdates::addCallableUpdate( function () use ( $reason ) {
				$this->logger->debug( 'Notify {mentee} about mentor change done by {performer}', [
					'mentee' => $this->mentee,
					'performer' => $this->performer
				] );
				EchoEvent::create( [
					'type' => 'mentor-changed',
					'title' => $this->userFactory->newFromUserIdentity( $this->newMentor )
						->getUserPage(),
					'extra' => [
						'mentee' => $this->mentee->getId(),
						'reason' => $reason
					],
					'agent' => $this->newMentor,
				] );

				if (
					$this->performer->equals( $this->newMentor ) &&
					$this->mentor !== null
				) {
					// mentee was claimed, notify old mentor as well
					EchoEvent::create( [
						'type' => 'mentee-claimed',
						'title' => $this->userFactory->newFromUserIdentity( $this->mentee )
							->getUserPage(),
						'extra' => [
							'mentor' => $this->mentor->getId(),
							'reason' => $reason
						],
						'agent' => $this->performer
					] );
				}
			} );
		}
	}

	/**
	 * Change mentor
	 *
	 * @param UserIdentity $newMentor New mentor to assign
	 * @param string $reason Reason for the change
	 * @return Status
	 */
	public function execute( UserIdentity $newMentor, string $reason ): Status {
		$this->newMentor = $newMentor;
		$status = $this->validate();
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->mentorStore->setMentorForUser( $this->mentee, $this->newMentor, MentorStore::ROLE_PRIMARY );
		$this->log( $reason );

		// If mentee's status is MENTORSHIP_DISABLED (=GE decided not to display the mentorship
		// module), enable the module, so the user can benefit from mentorship.
		// NOTE: This intentionally does not change MENTORSHIP_OPTOUT (as in that case, the
		// mentee explicitly said they're not interested in mentorship at all).
		if (
			$this->mentorManager->getMentorshipStateForUser( $this->mentee ) ===
			MentorManager::MENTORSHIP_DISABLED
		) {
			$this->mentorManager->setMentorshipStateForUser(
				$this->mentee,
				MentorManager::MENTORSHIP_ENABLED
			);
		}

		// Notify mentee about the change
		$this->notify( $reason );

		return $status;
	}
}
