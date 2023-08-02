<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use JsonSerializable;

class PraiseworthyConditions implements JsonSerializable {

	public const SETTING_MAX_EDITS = 'maxEdits';
	public const SETTING_MIN_EDITS = 'minEdits';
	public const SETTING_DAYS = 'days';

	/** @var int Maximum number of edits a mentee can have to be praiseworthy */
	private int $maxEdits;

	/** @var int Minimum number of edits a mentee must have to be praiseworthy */
	private int $minEdits;

	/**
	 * @var int
	 *
	 * To be considered praiseworthy, a mentee needs to make a certain number of edits
	 * (see $minEdits) in this amount of days to be praiseworthy.
	 */
	private int $days;

	/**
	 * @param int $maxEdits
	 * @param int $minEdits
	 * @param int $days
	 */
	public function __construct(
		int $maxEdits,
		int $minEdits,
		int $days
	) {
		$this->maxEdits = $maxEdits;
		$this->minEdits = $minEdits;
		$this->days = $days;
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return [
			self::SETTING_MAX_EDITS => $this->maxEdits,
			self::SETTING_MIN_EDITS => $this->minEdits,
			self::SETTING_DAYS => $this->days
		];
	}

	/**
	 * @return int Maximum number of edits a mentee can have to be praiseworthy
	 */
	public function getMaxEdits(): int {
		return $this->maxEdits;
	}

	/**
	 * @return int Minimum number of edits a mentee must have to be praiseworthy
	 */
	public function getMinEdits(): int {
		return $this->minEdits;
	}

	/**
	 * @return int To be considered praiseworthy, a mentee needs to make a certain number of
	 * edits (see ::getMinEdits) in this amount of days to be praiseworthy.
	 */
	public function getDays(): int {
		return $this->days;
	}
}
