<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Status;
use StatusValue;

abstract class TaskSuggesterFactory implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/**
	 * @return TaskSuggester
	 */
	abstract public function create();

	/**
	 * Create a TaskSuggester which just returns a given error.
	 * @param StatusValue $status
	 * @return ErrorForwardingTaskSuggester
	 */
	protected function createError( StatusValue $status ) {
		$msg = Status::wrap( $status )->getWikiText( false, false, 'en' );
		Util::logError( new WikiConfigException( $msg ) );
		return new ErrorForwardingTaskSuggester( $status );
	}

}
