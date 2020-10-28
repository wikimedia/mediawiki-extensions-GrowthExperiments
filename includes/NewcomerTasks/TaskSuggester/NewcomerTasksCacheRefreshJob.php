<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\GrowthExperimentsServices;
use Job;
use MediaWiki\MediaWikiServices;
use User;

/**
 * Refresh the newcomer tasks cache for a user.
 */
class NewcomerTasksCacheRefreshJob extends Job {

	/**
	 * @param array $params
	 */
	public function __construct( $params = [] ) {
		parent::__construct( 'newcomerTasksCacheRefreshJob', $params );
		$this->removeDuplicates = true;
	}

	/** @inheritDoc */
	public function run() {
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$taskSuggester = $growthServices->getTaskSuggesterFactory()->create();
		$user = User::newFromId( $this->params['userId'] );
		$taskSuggester->suggest(
			$user,
			$this->params['taskTypeFilters'],
			$this->params['topicFilters'],
			$this->params['limit'],
			null,
			false,
			false
		);
		return true;
	}
}
