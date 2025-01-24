<?php

namespace GrowthExperiments\NewcomerTasks;

use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Query logging table for newcomer tasks for a specific user.
 */
class NewcomerTasksLog {

	protected SelectQueryBuilder $queryBuilder;

	public function __construct( SelectQueryBuilder $queryBuilder ) {
		$this->queryBuilder = $queryBuilder;
	}

	/**
	 * Get the number of tasks the user has completed in the current day (for that user's timezone).
	 */
	public function count(): int {
		return $this->queryBuilder->caller( __METHOD__ )->fetchRowCount();
	}
}
