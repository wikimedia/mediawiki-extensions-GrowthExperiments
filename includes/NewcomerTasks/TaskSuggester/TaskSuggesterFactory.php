<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Status\StatusFormatter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use StatusValue;

abstract class TaskSuggesterFactory implements LoggerAwareInterface {

	use LoggerAwareTrait;

	public function __construct(
		protected StatusFormatter $statusFormatter
	) {
	}

	/**
	 * @param ConfigurationLoader|null $customConfigurationLoader Configuration loader to use instead of the default;
	 * used for querying different topic types (growth vs ores)
	 * @return TaskSuggester
	 */
	abstract public function create( ?ConfigurationLoader $customConfigurationLoader = null );

	/**
	 * Create a TaskSuggester which just returns a given error.
	 * @param StatusValue $status
	 * @return ErrorForwardingTaskSuggester
	 */
	protected function createError( StatusValue $status ) {
		Util::logException( new WikiConfigException(
			$this->statusFormatter->getWikiText( $status, [ 'lang' => 'en' ] )
		) );
		return new ErrorForwardingTaskSuggester( $status );
	}

}
