<?php

namespace GrowthExperiments\NewcomerTasks\AddSectionImage;

use LogPager;

/**
 * Query LogPager for section image recommendation submissions for a specific user.
 */
class SectionImageRecommendationSubmissionLog {

	private LogPager $logPager;

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
