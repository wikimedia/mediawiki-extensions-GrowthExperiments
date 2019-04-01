<?php

namespace GrowthExperiments;

use Job;
use User;

class MentorSaveJob extends Job {

	/**
	 * MentorSaveJob constructor.
	 *
	 * @param array $params
	 * @throws \MWException
	 */
	public function __construct( $params ) {
		parent::__construct( 'saveMentor', $params );
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
