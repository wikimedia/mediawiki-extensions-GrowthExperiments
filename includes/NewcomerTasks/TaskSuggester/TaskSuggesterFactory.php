<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Status\Status;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use StatusValue;

abstract class TaskSuggesterFactory implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/**
	 * @param ConfigurationLoader|null $customConfigurationLoader Configuration loader to use instead of the default;
	 * used for querying different topic types (growth vs ores)
	 * @return TaskSuggester
	 */
	abstract public function create( ConfigurationLoader $customConfigurationLoader = null );

	/**
	 * Create a TaskSuggester which just returns a given error.
	 * @param StatusValue $status
	 * @return ErrorForwardingTaskSuggester
	 */
	protected function createError( StatusValue $status ) {
		$msg = Status::wrap( $status )->getWikiText( false, false, 'en' );
		Util::logException( new WikiConfigException( $msg ) );
		return new ErrorForwardingTaskSuggester( $status );
	}

}
