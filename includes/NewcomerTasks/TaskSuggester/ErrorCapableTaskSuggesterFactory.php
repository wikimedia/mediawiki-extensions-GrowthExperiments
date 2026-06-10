<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Status\StatusFormatter;
use StatusValue;

/**
 * Base class for task suggester factories that may produce an error suggester
 * (e.g. when the wiki configuration is invalid) instead of a working one.
 */
abstract class ErrorCapableTaskSuggesterFactory extends TaskSuggesterFactory {

	public function __construct(
		protected StatusFormatter $statusFormatter
	) {
	}

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
