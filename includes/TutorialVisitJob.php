<?php

namespace GrowthExperiments;

use GrowthExperiments\HomepageModules\Tutorial;
use Job;
use Title;
use User;

class TutorialVisitJob extends Job {

	/**
	 * TutorialVisitJob constructor.
	 * @param array $params
	 */
	public function __construct( $params ) {
		parent::__construct(
			'tutorialVisit',
			Title::makeTitle( NS_SPECIAL, 'Blankpage' ),
			$params
		);
	}

	/**
	 * @return bool
	 */
	public function ignoreDuplicates() {
		return true;
	}

	public function run() {
		$user = User::newFromId( $this->params['userId'] );
		$user->setOption( Tutorial::TUTORIAL_PREF, 1 );
		$user->saveSettings();
		return true;
	}
}
