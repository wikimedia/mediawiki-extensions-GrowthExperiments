<?php

namespace GrowthExperiments;

use Job;
use SpecialPage;
use User;

class MentorSaveJob extends Job {

	/**
	 * MentorSaveJob constructor.
	 * @param int $userId
	 * @param int $mentorId
	 * @throws \MWException
	 */
	public function __construct( $userId, $mentorId ) {
		parent::__construct(
			'saveMentor',
			SpecialPage::getTitleFor( 'Homepage' ),
			[
				'userId' => $userId,
				'mentorId' => $mentorId,
			]
		);
	}

	/**
	 * @return bool
	 */
	public function ignoreDuplicates() {
		return true;
	}

	/**
	 * There's currently no use case for changing mentor. If multiple jobs
	 * are created for the same user (by mistake) only one needs to execute.
	 *
	 * @return array
	 */
	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();
		unset( $info['params']['mentorId'] );
		return $info;
	}

	public function run() {
		$user = User::newFromId( $this->params['userId'] );
		$user->setOption( Mentor::MENTOR_PREF, $this->params['mentorId'] );
		$user->saveSettings();
		return true;
	}
}
