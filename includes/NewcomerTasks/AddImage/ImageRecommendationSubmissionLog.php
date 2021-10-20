<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use LogPager;

/**
 * Query LogPager for image recommendation submissions for a specific user.
 */
class ImageRecommendationSubmissionLog {

	/** @var LogPager */
	private $logPager;

	/**
	 * @param LogPager $logPager
	 */
	public function __construct( LogPager $logPager ) {
		$this->logPager = $logPager;
	}

	/**
	 * Get the number of tasks the user has completed in the current day (for that user's timezone).
	 *
	 * @return int
	 */
	public function count(): int {
		$this->logPager->doQuery();
		return $this->logPager->getResult()->count();
	}
}
