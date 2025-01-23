<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use MediaWiki\User\UserIdentity;

/**
 * A value object representing a Growth mentor
 *
 * This class should be aware of all aspects involved in being a Growth mentor (including the
 * mentor's custom introduction message, if there is any, or whether they're automatically
 * assigned to newcomers).
 */
class Mentor implements IMentorWeights {

	private UserIdentity $mentorUser;
	private ?string $introText;
	private string $defaultIntroText;
	/** @var int One of Mentor::WEIGHT_* */
	private int $weight;

	/**
	 * @param UserIdentity $mentorUser
	 * @param string|null $introText if null, $defaultIntroText will be used instead
	 * @param string $defaultIntroText
	 * @param int $weight
	 */
	public function __construct(
		UserIdentity $mentorUser,
		?string $introText,
		string $defaultIntroText,
		int $weight
	) {
		$this->mentorUser = $mentorUser;
		$this->introText = $introText;
		$this->defaultIntroText = $defaultIntroText;
		$this->weight = $weight;
	}

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
	 * @return int Mentor's weight (one of Mentor::WEIGHT_*)
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

	public function setWeight( int $weight ): void {
		$this->weight = $weight;
	}
}
