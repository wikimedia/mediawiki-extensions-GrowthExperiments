<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\User\UserIdentity;

class Mentor {

	/**
	 * @var UserIdentity
	 */
	private $mentorUser;

	/**
	 * @var string
	 */
	private $introText;

	/**
	 * @param UserIdentity $mentorUser
	 * @param string $introText
	 */
	public function __construct( UserIdentity $mentorUser, string $introText ) {
		$this->mentorUser = $mentorUser;
		$this->introText = $introText;
	}

	/**
	 * @return UserIdentity
	 */
	public function getUserIdentity(): UserIdentity {
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
