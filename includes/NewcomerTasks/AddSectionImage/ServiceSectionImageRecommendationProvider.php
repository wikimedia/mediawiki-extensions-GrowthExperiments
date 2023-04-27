<?php
namespace GrowthExperiments\NewcomerTasks\AddSectionImage;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Linker\LinkTarget;
use Status;

/**
 * Provides section image recommendations via the Image Suggestion API.
 */

class ServiceSectionImageRecommendationProvider implements ImageRecommendationProvider {

	public function get( LinkTarget $title, TaskType $taskType ): Status {
		// FIXME implement
		$status = new Status();
		$status->setResult( true, $taskType );
		return $status;
	}
}
