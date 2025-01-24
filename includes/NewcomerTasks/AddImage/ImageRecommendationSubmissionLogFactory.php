<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\NewcomerTasksLog;
use GrowthExperiments\NewcomerTasks\NewcomerTasksLogFactory;
use MediaWiki\User\UserIdentity;

class ImageRecommendationSubmissionLogFactory extends NewcomerTasksLogFactory {

	public function newImageRecommendationSubmissionLog(
		UserIdentity $user
	): NewcomerTasksLog {
		return new NewcomerTasksLog( $this->getQueryBuilder( $user, 'addimage' ) );
	}
}
