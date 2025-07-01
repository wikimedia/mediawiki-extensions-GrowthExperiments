<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\JobQueue\Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;

/**
 * Job to reassign all mentees operated by a given mentor
 *
 * The following job parameters are required:
 *  - mentorId: user ID of the mentor to process
 *  - reassignMessageKey: Message to store in logs as well as in notifications to mentees
 */
class ReassignMenteesJob extends Job {

	public const JOB_NAME = 'reassignMenteesJob';

	private Config $config;
	private UserIdentityLookup $userIdentityLookup;
	private MentorStore $mentorStore;
	private ReassignMenteesFactory $reassignMenteesFactory;
	private LoggerInterface $logger;

	public function __construct(
		array $params,
		Config $config,
		UserIdentityLookup $userIdentityLookup,
		MentorStore $mentorStore,
		ReassignMenteesFactory $reassignMenteesFactory
	) {
		parent::__construct( self::JOB_NAME, $params );

		$this->config = $config;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->mentorStore = $mentorStore;
		$this->reassignMenteesFactory = $reassignMenteesFactory;

		// TODO: this is not a service yet, but should be
		$this->logger = LoggerFactory::getInstance( 'GrowthExperiments' );
	}

	/**
	 * @inheritDoc
	 */
	public function ignoreDuplicates() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();

		// When deduplicating, ignore performerId, reassignMessageKey and
		// reassignMessageAdditionalParams. The reason for deduplication
		// is to avoid reassigning mentees assigned to the same mentor more
		// than once (see T322374).
		foreach ( [ 'performerId', 'reassignMessageKey', 'reassignMessageAdditionalParams' ] as $ignoredParam ) {
			if ( isset( $info['params'][$ignoredParam] ) ) {
				unset( $info['params'][$ignoredParam] );
			}
		}

		return $info;
	}

	private function getBatchSize(): int {
		return $this->config->get( 'GEMentorshipReassignMenteesBatchSize' );
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$mentor = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['mentorId'] );
		$performer = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['performerId'] );
		if ( !$mentor || !$performer ) {
			$this->logger->error(
				'ReassignMenteesJob trigerred with invalid parameters',
				[
					'performerId' => $this->params['performerId'],
					'mentorId' => $this->params['mentorId'],
				]
			);
			return false;
		}

		$reassignMentees = $this->reassignMenteesFactory->newReassignMentees(
			$performer,
			$mentor,
			RequestContext::getMain()
		);
		$status = $reassignMentees->doReassignMentees(
			$this->getBatchSize(),
			$this->params['reassignMessageKey'],
			...$this->params['reassignMessageAdditionalParams']
		);
		$this->logger->info( 'ReassignMenteesJob finished reassignment with {status} status', [
			'status' => $status,
		] );

		if ( $status ) {
			if ( $this->mentorStore->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) ) {
				$this->logger->info( 'ReassignMenteesJob did not reassign all mentees, scheduling new job', [
					'mentor' => $mentor->getName(),
				] );
				$reassignMentees->scheduleReassignMenteesJob(
					$this->params['reassignMessageKey'],
					...$this->params['reassignMessageAdditionalParams']
				);
			} else {
				$this->logger->info( 'ReassignMenteesJob finished reassigning all mentees', [
					'mentor' => $mentor->getName(),
				] );
			}
		}

		return true;
	}
}
