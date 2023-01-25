<?php

namespace GrowthExperiments\Mentorship;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityLookup;
use RequestContext;

/**
 * Job to reassign all mentees operated by a given mentor
 *
 * The following job parameters are required:
 *  - mentorId: user ID of the mentor to process
 *  - reassignMessageKey: Message to store in logs as well as in notifications to mentees
 */
class ReassignMenteesJob extends Job implements GenericParameterJob {

	private UserIdentityLookup $userIdentityLookup;
	private ReassignMenteesFactory $reassignMenteesFactory;

	/**
	 * @inheritDoc
	 */
	public function __construct( $params = null ) {
		parent::__construct( 'reassignMenteesJob', $params );

		// init services
		$services = MediaWikiServices::getInstance();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->reassignMenteesFactory = GrowthExperimentsServices::wrap( $services )
			->getReassignMenteesFactory();
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

	/**
	 * @inheritDoc
	 */
	public function run() {
		$mentor = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['mentorId'] );
		$performer = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['performerId'] );
		if ( !$mentor || !$performer ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->error(
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
		$reassignMentees->doReassignMentees(
			$this->params['reassignMessageKey'],
			...$this->params['reassignMessageAdditionalParams']
		);

		return true;
	}
}
