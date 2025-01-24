<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use GrowthExperiments\NewcomerTasks\NewcomerTasksLog;
use GrowthExperiments\NewcomerTasks\NewcomerTasksLogFactory;
use MediaWiki\User\UserIdentity;

class LinkRecommendationSubmissionLogFactory extends NewcomerTasksLogFactory {

	public function newLinkRecommendationSubmissionLog(
		UserIdentity $user
	): NewcomerTasksLog {
		return new NewcomerTasksLog( $this->getQueryBuilder( $user, 'addlink' ) );
	}
}
