<?php

namespace GrowthExperiments\Mentorship;

use User;

class Mentor {

	/**
	 * @var User
	 */
	private $mentorUser;

	/**
	 * @var string
	 */
	private $introText;

	/**
	 * @param User $mentorUser
	 * @param string $introText
	 */
	public function __construct( User $mentorUser, string $introText ) {
		$this->mentorUser = $mentorUser;
		$this->introText = $introText;
	}

	/**
	 * @return User
	 */
	public function getMentorUser() {
		return $this->mentorUser;
	}

	/**
	 * Returns the introduction text for a mentor.
	 * @return string
	 */
	public function getIntroText() {
		return $this->introText;
	}

}
