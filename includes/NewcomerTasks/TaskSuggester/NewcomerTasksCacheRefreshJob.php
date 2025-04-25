<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use MediaWiki\JobQueue\GenericParameterJob;
use MediaWiki\JobQueue\Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

/**
 * Refresh the newcomer tasks cache for a user.
 */
class NewcomerTasksCacheRefreshJob extends Job implements GenericParameterJob {

	/** @inheritDoc */
	public function __construct( array $params ) {
		parent::__construct( 'newcomerTasksCacheRefreshJob', $params );
		$this->removeDuplicates = true;
	}

	/** @inheritDoc */
	public function run() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$newcomerTaskOptions = $growthServices->getNewcomerTasksUserOptionsLookup();
		$taskSuggester = $growthServices->getTaskSuggesterFactory()->create();
		$user = User::newFromId( $this->params['userId'] );
		$taskSuggester->suggest(
			$user,
			new TaskSetFilters(
				$newcomerTaskOptions->getTaskTypeFilter( $user ),
				$newcomerTaskOptions->getTopics( $user ),
				$newcomerTaskOptions->getTopicsMatchMode( $user )
			),
			SearchTaskSuggester::DEFAULT_LIMIT,
			null,
			[ 'useCache' => false ]
		);
		return true;
	}
}
