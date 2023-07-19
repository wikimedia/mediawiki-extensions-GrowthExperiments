<?php

namespace GrowthExperiments\Mentorship;

use DeferredUpdates;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Mentorship\Store\MentorStore;
use ManualLogEntry;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Status;
use User;
use Wikimedia\Rdbms\IConnectionProvider;

class ChangeMentor {
	private UserIdentity $mentee;
	private ?UserIdentity $mentor;
	private ?UserIdentity $newMentor;
	private UserIdentity $performer;
	private LoggerInterface $logger;
	private MentorManager $mentorManager;
	private MentorStore $mentorStore;
	private UserFactory $userFactory;
	private IConnectionProvider $connectionProvider;
	private ?User $menteeUser = null;

	/**
	 * @param UserIdentity $mentee Mentee's user object
	 * @param UserIdentity $performer Performer's user object
	 * @param LoggerInterface $logger
	 * @param Mentor|null $mentor Old mentor
	 * @param MentorManager $mentorManager
	 * @param MentorStore $mentorStore
	 * @param UserFactory $userFactory
	 * @param IConnectionProvider $connectionProvider
	 */
	public function __construct(
		UserIdentity $mentee,
		UserIdentity $performer,
		LoggerInterface $logger,
		?Mentor $mentor,
		MentorManager $mentorManager,
		MentorStore $mentorStore,
		UserFactory $userFactory,
		IConnectionProvider $connectionProvider
	) {
		$this->logger = $logger;

		$this->performer = $performer;
		$this->mentee = $mentee;
		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
		$this->userFactory = $userFactory;
		$this->connectionProvider = $connectionProvider;
		$this->mentor = $mentor ? $mentor->getUserIdentity() : null;
	}

	/**
	 * Was mentee's mentor already changed?
	 *
	 * @return bool
	 */
	public function wasMentorChanged(): bool {
		$res = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [ 'log_page' ] )
			->from( 'logging' )
			->where( [
				'log_type' => 'growthexperiments',
				'log_namespace' => NS_USER,
				'log_title' => Title::makeTitle( NS_USER, $this->mentee->getName() )->getDbKey()
			] )
			->caller( __METHOD__ )
			->fetchRow();
		return (bool)$res;
	}

	/**
	 * Log mentor change
	 *
	 * @param string $reason Reason for the change
	 * @param bool $forceBot Whether to mark this log entry as bot-made
	 */
	protected function log( string $reason, bool $forceBot ) {
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
		$logEntry->setTarget( $this->getMenteeUser()->getUserPage() );
		$logEntry->setComment( $reason );
		if ( $forceBot ) {
			// Don't spam RecentChanges with bulk changes (T304428)
			$logEntry->setForceBotFlag( true );
		}
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

		if ( !$this->getMenteeUser()->isNamed() ) {
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

		if ( $this->mentorManager->getMentorshipStateForUser( $this->mentee ) ===
			MentorManager::MENTORSHIP_OPTED_OUT ) {
			$status->fatal(
				'growthexperiments-homepage-claimmentee-opt-out',
				$this->mentee->getName()
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
				Event::create( [
					'type' => 'mentor-changed',
					'title' => $this->getMenteeUser()->getUserPage(),
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
					Event::create( [
						'type' => 'mentee-claimed',
						'title' => $this->getMenteeUser()->getUserPage(),
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
	 * Does user have mentorship-consuming features enabled?
	 *
	 * This is used to skip notifications about mentorship changes when the user doesn't actually
	 * have relevant features enabled.
	 *
	 * @note This is a separate method to make unit testing possible
	 * (HomepageHooks::isHomepageEnabled and HelpPanel::shouldShowHelpPanelToUser both use global
	 * state)
	 * @param UserIdentity $user
	 * @return bool
	 */
	protected function isMentorshipEnabledForUser( UserIdentity $user ): bool {
		return HomepageHooks::isHomepageEnabled( $user )
			|| HelpPanel::shouldShowHelpPanelToUser( $user );
	}

	/**
	 * Change mentor
	 *
	 * This sets the new primary mentor in the database and adds a log under Special:Log. In most
	 * cases, it also notifies the mentee about the mentor change. Notification is only
	 * sent if all of the following conditions are true:
	 *
	 * 	1) Mentee has access to Special:Homepage or Help panel
	 * 	2) Mentee has mentorship enabled (MENTORSHIP_ENABLED)
	 *
	 * If mentee's mentorship state is MENTORSHIP_DISABLED, access to mentorship is enabled by
	 * this method, except when $bulkChange is true, but a notification is not sent.
	 *
	 * @param UserIdentity $newMentor New mentor to assign
	 * @param string $reason Reason for the change
	 * @param bool $bulkChange Is this a part of a bulk mentor reassignment (used by
	 * ReassignMentees class)
	 * @return Status
	 */
	public function execute(
		UserIdentity $newMentor,
		string $reason,
		bool $bulkChange = false
	): Status {
		$this->newMentor = $newMentor;
		$status = $this->validate();
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->mentorStore->setMentorForUser( $this->mentee, $this->newMentor, MentorStore::ROLE_PRIMARY );
		$this->log( $reason, $bulkChange );

		if ( $this->isMentorshipEnabledForUser( $this->mentee ) ) {
			$mentorshipState = $this->mentorManager->getMentorshipStateForUser( $this->mentee );

			if ( $mentorshipState === MentorManager::MENTORSHIP_ENABLED ) {
				$this->notify( $reason );
			}

			if ( !$bulkChange && $mentorshipState === MentorManager::MENTORSHIP_DISABLED ) {
				// For non-bulk changes when MENTORSHIP_DISABLED (=GrowthExperiments decided not
				// to include the mentorship module), set the state to MENTORSHIP_ENABLED to ensure
				// the user can benefit from mentorship (T327206).
				// NOTE: Do not enable for MENTORSHIP_OPTOUT, as that means "I'm not interested
				// in being mentored at all" (as an explicit user choice).
				// NOTE: Call to notify() is intentionally above this condition. For users who
				// didn't have mentorship access before, notification "Your mentor has changed"
				// would be confusing (T330035).

				$this->mentorManager->setMentorshipStateForUser(
					$this->mentee,
					MentorManager::MENTORSHIP_ENABLED
				);
			}
		}

		return $status;
	}

	private function getMenteeUser(): User {
		if ( !$this->menteeUser ) {
			$this->menteeUser = $this->userFactory->newFromUserIdentity( $this->mentee );
		}
		return $this->menteeUser;
	}
}
