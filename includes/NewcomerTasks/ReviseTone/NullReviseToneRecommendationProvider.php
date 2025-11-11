<?php

namespace GrowthExperiments\NewcomerTasks\ReviseTone;

use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use StatusValue;

/**
 * Null provider for testing - returns no recommendations
 */
class NullReviseToneRecommendationProvider implements RecommendationProvider {
	/**
	 * @param LinkTarget $title Page to check
	 * @param TaskType $taskType Task type
	 * @return StatusValue Always fatal
	 */
	public function get( LinkTarget $title, TaskType $taskType ) {
		return StatusValue::newFatal(
			'rawmessage',
			'No tone check recommendations available from null provider'
		);
	}
}
