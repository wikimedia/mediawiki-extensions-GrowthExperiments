<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\User\UserIdentity;

/**
 * A value object representing a Growth mentor
 *
 * This class should be aware of all aspects involved in being a Growth mentor (including the
 * mentor's custom introduction message, if there is any, or whether they're automatically
 * assigned to newcomers).
 */
class Mentor {

	private UserIdentity $mentorUser;
	private ?string $introText;
	private string $defaultIntroText;
	private bool $autoAssigned;
	private int $weight;

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
