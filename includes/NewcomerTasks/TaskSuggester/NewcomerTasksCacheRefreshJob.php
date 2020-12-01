<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GenericParameterJob;
use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\MediaWikiServices;
use User;

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
			$newcomerTaskOptions->getTaskTypeFilter( $user ),
			$newcomerTaskOptions->getTopicFilter( $user ),
			SearchTaskSuggester::DEFAULT_LIMIT,
			null,
			false,
			false
		);
		return true;
	}
}
