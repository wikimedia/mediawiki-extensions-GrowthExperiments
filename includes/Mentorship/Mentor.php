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
	 * @var string
	 */
	private $defaultIntroText;

	/**
	 * @param UserIdentity $mentorUser
	 * @param string|null $introText if null, $defaultIntroText will be used instead
	 * @param string $defaultIntroText
	 */
	public function __construct(
		UserIdentity $mentorUser,
		?string $introText,
		string $defaultIntroText
	) {
		$this->mentorUser = $mentorUser;
		$this->introText = $introText;
		$this->defaultIntroText = $defaultIntroText;
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
		return $this->introText ?? $this->defaultIntroText;
	}
}
