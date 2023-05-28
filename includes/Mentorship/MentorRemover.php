<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use IContextSource;
use MediaWiki\User\UserIdentity;
use StatusValue;

class MentorRemover {

	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private ReassignMenteesFactory $reassignMenteesFactory;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param ReassignMenteesFactory $reassignMenteesFactory
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		ReassignMenteesFactory $reassignMenteesFactory
	) {
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
		$this->reassignMenteesFactory = $reassignMenteesFactory;
	}

	/**
	 * @param UserIdentity $performer
	 * @param UserIdentity $mentor
	 * @param string $reason
	 * @param IContextSource $context
	 * @return StatusValue
	 */
	public function removeMentor(
		UserIdentity $performer,
		UserIdentity $mentor,
		string $reason,
		IContextSource $context
	): StatusValue {
		$status = $this->mentorWriter->removeMentor(
			$this->mentorProvider->newMentorFromUserIdentity( $mentor ),
			$performer,
			$reason
		);
		if ( $status->isOK() ) {
			$this->reassignMenteesFactory->newReassignMentees(
				$performer,
				$mentor,
				$context
			)->reassignMentees(
				'growthexperiments-quit-mentorship-reassign-mentees-log-message-removed',
				$mentor->getName()
			);
		}
		return $status;
	}
}
