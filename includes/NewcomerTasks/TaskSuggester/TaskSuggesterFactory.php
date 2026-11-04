<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use Psr\Log\LoggerInterface;

abstract class TaskSuggesterFactory {

	public function __construct(
		protected LoggerInterface $logger
	) {
	}

	/**
	 * @return TaskSuggester
	 */
	abstract public function create();

}
