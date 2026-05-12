<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserIdentity;
use StatusValue;

class MentorRemover {

	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private ReassignMenteesFactory $reassignMenteesFactory;

	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		ReassignMenteesFactory $reassignMenteesFactory
	) {
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
		$this->reassignMenteesFactory = $reassignMenteesFactory;
	}

	public function removeMentor(
		UserIdentity $performer,
		UserIdentity $mentor,
		string $reason,
		MessageLocalizer $messageLocalizer
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
				$messageLocalizer
			)->reassignMentees(
				'growthexperiments-quit-mentorship-reassign-mentees-log-message-removed',
				$performer->getName()
			);
		}
		return $status;
	}
}
