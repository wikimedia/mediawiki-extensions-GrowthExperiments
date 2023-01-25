<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use IContextSource;
use LogEventsList;
use LogPager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class ChangeMentorFactory {

	private LoggerInterface $logger;
	private MentorManager $mentorManager;
	private MentorStore $mentorStore;
	private UserFactory $userFactory;

	/**
	 * @param LoggerInterface $logger
	 * @param MentorManager $mentorManager
	 * @param MentorStore $mentorStore
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		LoggerInterface $logger,
		MentorManager $mentorManager,
		MentorStore $mentorStore,
		UserFactory $userFactory
	) {
		$this->logger = $logger;
		$this->mentorManager = $mentorManager;
		$this->mentorStore = $mentorStore;
		$this->userFactory = $userFactory;
	}

	/**
	 * @param UserIdentity $mentee
	 * @param UserIdentity $performer
	 * @param IContextSource $context
	 * @return ChangeMentor
	 */
	public function newChangeMentor(
		UserIdentity $mentee,
		UserIdentity $performer,
		IContextSource $context
	): ChangeMentor {
		return new ChangeMentor(
			$mentee,
			$performer,
			$this->logger,
			$this->mentorManager->getMentorForUserIfExists( $mentee ),
			new LogPager(
				new LogEventsList( $context ),
				[ 'growthexperiments' ],
				'',
				$this->userFactory->newFromUserIdentity(
					$mentee
				)->getUserPage()
			),
			$this->mentorStore,
			$this->userFactory
		);
	}
}
