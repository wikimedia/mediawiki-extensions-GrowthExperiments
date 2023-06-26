<?php

namespace GrowthExperiments\NewcomerTasks\AddSectionImage;

use GrowthExperiments\NewcomerTasks\NewcomerTasksLog;
use GrowthExperiments\NewcomerTasks\NewcomerTasksLogFactory;
use MediaWiki\User\UserIdentity;

class SectionImageRecommendationSubmissionLogFactory extends NewcomerTasksLogFactory {

	/**
	 * @param UserIdentity $user
	 * @return NewcomerTasksLog
	 */
	public function newSectionImageRecommendationSubmissionLog(
		UserIdentity $user
	): NewcomerTasksLog {
		return new NewcomerTasksLog( $this->getQueryBuilder( $user, 'addsectionimage' ) );
	}
}
