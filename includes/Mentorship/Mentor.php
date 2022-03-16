<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\User\UserIdentity;

class Mentor {

	/**
	 * @var UserIdentity
	 */
	private $mentorUser;

	/**
	 * @var string|null
	 */
	private $introText;

	/**
	 * @var string
	 */
	private $defaultIntroText;

	/**
	 * @var bool Is the mentor automatically assigned to the mentees?
	 */
	private $autoAssigned;

	/**
	 * @var int
	 */
	private $weight;

	/**
	 * @param UserIdentity $mentorUser
	 * @param string|null $introText if null, $defaultIntroText will be used instead
	 * @param string $defaultIntroText
	 * @param bool $autoAssigned
	 * @param int $weight
	 */
	public function __construct(
		UserIdentity $mentorUser,
		?string $introText,
		string $defaultIntroText,
		bool $autoAssigned,
		int $weight
	) {
		$this->mentorUser = $mentorUser;
		$this->introText = $introText;
		$this->defaultIntroText = $defaultIntroText;
		$this->autoAssigned = $autoAssigned;
		$this->weight = $weight;
	}

	/**
	 * @return UserIdentity
	 */
	public function getUserIdentity(): UserIdentity {
		return $this->mentorUser;
	}

	/**
	 * @return bool Is a custom intro text used?
	 */
	public function hasCustomIntroText(): bool {
		return $this->introText !== null;
	}

	/**
	 * Returns the introduction text for a mentor.
	 * @return string
	 */
	public function getIntroText() {
		return $this->introText ?? $this->defaultIntroText;
	}

	/**
	 * @return bool Is the mentor automatically assigned to newcomers?
	 */
	public function getAutoAssigned(): bool {
		return $this->autoAssigned;
	}

	/**
	 * @return int Mentor's weight (one of MentorWeightManager::WEIGHT_*)
	 */
	public function getWeight(): int {
		return $this->weight;
	}

	/**
	 * @param string|null $introText Null to use the default message
	 */
	public function setIntroText( ?string $introText ): void {
		$this->introText = $introText;
	}

	/**
	 * @param bool $autoAssigned
	 */
	public function setAutoAssigned( bool $autoAssigned ): void {
		$this->autoAssigned = $autoAssigned;
	}

	/**
	 * @param int $weight
	 */
	public function setWeight( int $weight ): void {
		$this->weight = $weight;
	}
}
