<?php

namespace GrowthExperiments\UserImpact;

use DatePeriod;
use JsonSerializable;

/**
 * Value object representing an editing streak. It has a start date, an end date, and a count of the total number
 * of edits for this date range.
 */
class EditingStreak implements JsonSerializable {

	/** @var DatePeriod|null */
	private ?DatePeriod $datePeriod;
	/** @var int */
	private int $totalEditCountForPeriod;

	/**
	 * @param DatePeriod|null $datePeriod
	 * @param int $totalEditCountForPeriod
	 */
	public function __construct(
		?DatePeriod $datePeriod = null,
		int $totalEditCountForPeriod = 0
	) {
		$this->datePeriod = $datePeriod;
		$this->totalEditCountForPeriod = $totalEditCountForPeriod;
	}

	/** @inheritDoc */
	public function jsonSerialize() {
		return $this->datePeriod ?
		[
			'datePeriod' => [
				'start' => $this->datePeriod->getStartDate()->format( 'Y-m-d' ),
				'end' => $this->datePeriod->getEndDate()->format( 'Y-m-d' ),
				'days' => $this->getStreakNumberOfDays()
			],
			'totalEditCountForPeriod' => $this->totalEditCountForPeriod
		] : '';
	}

	/**
	 * @return int
	 */
	public function getStreakNumberOfDays(): int {
		return $this->datePeriod ?
			$this->datePeriod->getEndDate()->diff( $this->datePeriod->getStartDate() )->days + 1 :
			0;
	}

	/**
	 * @return DatePeriod|null
	 */
	public function getDatePeriod(): ?DatePeriod {
		return $this->datePeriod;
	}

	/**
	 * @param DatePeriod $datePeriod
	 */
	public function setDatePeriod( DatePeriod $datePeriod ): void {
		$this->datePeriod = $datePeriod;
	}

	/**
	 * @return int
	 */
	public function getTotalEditCountForPeriod(): int {
		return $this->totalEditCountForPeriod;
	}

	/**
	 * @param int $totalEditCountForPeriod
	 */
	public function setTotalEditCountForPeriod( int $totalEditCountForPeriod ): void {
		$this->totalEditCountForPeriod = $totalEditCountForPeriod;
	}
}
