<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;

class ReassignMenteesFactory {

	private ILoadBalancer $dbLoadBalancer;
	private IMentorManager $mentorManager;
	private MentorProvider $mentorProvider;
	private MentorStore $mentorStore;
	private ChangeMentorFactory $changeMentorFactory;
	private JobQueueGroupFactory $jobQueueGroupFactory;
	private FormatterFactory $formatterFactory;

	/**
	 * @param ILoadBalancer $dbLoadBalancer
	 * @param IMentorManager $mentorManager
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 * @param ChangeMentorFactory $changeMentorFactory
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param FormatterFactory $formatterFactory
	 */
	public function __construct(
		ILoadBalancer $dbLoadBalancer,
		IMentorManager $mentorManager,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore,
		ChangeMentorFactory $changeMentorFactory,
		JobQueueGroupFactory $jobQueueGroupFactory,
		FormatterFactory $formatterFactory
	) {
		$this->dbLoadBalancer = $dbLoadBalancer;
		$this->mentorManager = $mentorManager;
		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
		$this->changeMentorFactory = $changeMentorFactory;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->formatterFactory = $formatterFactory;
	}

	/**
	 * @param UserIdentity $performer
	 * @param UserIdentity $mentor
	 * @param IContextSource $context
	 * @return ReassignMentees
	 */
	public function newReassignMentees(
		UserIdentity $performer,
		UserIdentity $mentor,
		IContextSource $context
	): ReassignMentees {
		$reassignMentees = new ReassignMentees(
			$this->dbLoadBalancer->getConnection( DB_PRIMARY ),
			$this->mentorManager,
			$this->mentorProvider,
			$this->mentorStore,
			$this->changeMentorFactory,
			$this->jobQueueGroupFactory,
			$this->formatterFactory->getStatusFormatter( $context ),
			$performer,
			$mentor,
			$context
		);
		$reassignMentees->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $reassignMentees;
	}
}
