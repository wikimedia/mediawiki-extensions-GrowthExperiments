<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\NewcomerTasksLog;
use GrowthExperiments\NewcomerTasks\NewcomerTasksLogFactory;
use MediaWiki\User\UserIdentity;

class ImageRecommendationSubmissionLogFactory extends NewcomerTasksLogFactory {

	/**
	 * @param UserIdentity $user
	 * @return NewcomerTasksLog
	 */
	public function newImageRecommendationSubmissionLog(
		UserIdentity $user
	): NewcomerTasksLog {
		return new NewcomerTasksLog( $this->getQueryBuilder( $user, 'addimage' ) );
	}
}
