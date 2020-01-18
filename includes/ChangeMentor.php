<?php

namespace GrowthExperiments;

use EchoEvent;
use IContextSource;
use LogPager;
use ManualLogEntry;
use Psr\Log\LoggerInterface;
use Status;
use User;

class ChangeMentor {
	/**
	 * @var User
	 */
	private $mentee;
	/**
	 * @var User|null
	 */
	private $mentor;
	/**
	 * @var User|null
	 */
	private $newMentor;
	/**
	 * @var User
	 */
	private $performer;
	/**
	 * @var IContextSource
	 */
	private $context;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var LogPager
	 */
	private $logPager;

	/**
	 * @param User $mentee Mentee's user object
	 * @param User $performer Performer's user object
	 * @param IContextSource $context Context
	 * @param LoggerInterface $logger Logger
	 * @param Mentor|bool $mentor
	 * @param LogPager $logPager
	 */
	public function __construct(
		User $mentee,
		User $performer,
		IContextSource $context,
		LoggerInterface $logger,
		$mentor,
		LogPager $logPager
	) {
		$this->logger = $logger;

		$this->performer = $performer;
		$this->context = $context;
		$this->mentee = $mentee;
		$this->logPager = $logPager;
		if ( $mentor instanceof Mentor ) {
			$this->mentor = $mentor->getMentorUser();
		}
	}

	/**
	 * Was mentee's mentor already changed?
	 *
	 * @return bool
	 */
	public function wasMentorChanged() {
		$this->logPager->doQuery();
		return $this->logPager->getResult()->fetchRow();
	}

	/**
	 * Log mentor change
	 *
	 * @param string $reason Reason for the change
	 */
	private function log( $reason ) {
		$this->logger->debug(
			'Logging mentor change for {mentee} from {oldMentor} to {newMentor}', [
				'mentee' => $this->mentee,
				'oldMentor' => $this->mentor,
				'newMentor' => $this->newMentor
		] );

		$logEntry = new ManualLogEntry(
			'growthexperiments',
			$this->mentor ?
				'claimmentee' :
				'claimmentee-no-previous-mentor'
		);
		$logEntry->setPerformer( $this->performer );
		$logEntry->setTarget( $this->mentee->getUserPage() );
		$logEntry->setComment( $reason );
		if ( $this->mentor ) {
			// $this->mentor is null when no mentor existed previously
			$logEntry->setParameters( [
				'4::previous-mentor' => $this->mentor->getName()
			] );
		}
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}

	private function validate() {
		$this->logger->debug(
			'Validating mentor change for {mentee} from {oldMentor} to {newMentor}', [
				'mentee' => $this->mentee,
				'oldMentor' => $this->mentor,
				'newMentor' => $this->newMentor
		] );
		$status = Status::newGood();

		if ( $this->mentee->getId() === 0 ) {
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
	private function notify( $reason ) {
		if ( \ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			$this->logger->debug( 'Notify {mentee} about mentor change done by {performer}', [
				'mentee' => $this->mentee,
				'performer' => $this->performer
			] );
			EchoEvent::create( [
				'type' => 'mentor-changed',
				'title' => $this->newMentor->getUserPage(),
				'extra' => [
					'mentee' => $this->mentee->getId(),
					'reason' => $reason
				],
				'agent' => $this->newMentor,
			] );
		}
	}

	/**
	 * Change mentor
	 *
	 * @param User $newMentor New mentor to assign
	 * @param string $reason Reason for the change
	 * @return Status
	 */
	public function execute( User $newMentor, $reason ) {
		$this->newMentor = $newMentor;
		$status = $this->validate();
		if ( !$status->isOK() ) {
			return $status;
		}

		Mentor::saveMentor( $this->mentee, $this->newMentor );
		$this->log( $reason );

		// Notify mentee about the change
		$this->notify( $reason );

		return $status;
	}
}
