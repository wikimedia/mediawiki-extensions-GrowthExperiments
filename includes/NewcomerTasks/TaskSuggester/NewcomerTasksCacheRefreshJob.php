<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use LogicException;
use MediaWiki\JobQueue\Job;
use MediaWiki\User\UserIdentityLookup;

/**
 * Refresh the newcomer tasks cache for a user.
 */
class NewcomerTasksCacheRefreshJob extends Job {

	public const JOB_NAME = 'newcomerTasksCacheRefreshJob';

	private UserIdentityLookup $userIdentityLookup;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private TaskSuggesterFactory $taskSuggesterFactory;

	/** @inheritDoc */
	public function __construct(
		array $params,
		UserIdentityLookup $userIdentityLookup,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		TaskSuggesterFactory $taskSuggesterFactory
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->removeDuplicates = true;

		$this->userIdentityLookup = $userIdentityLookup;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
	}

	/** @inheritDoc */
	public function run() {
		$taskSuggester = $this->taskSuggesterFactory->create();
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['userId'] );
		if ( $userIdentity === null ) {
			throw new LogicException(
				__CLASS__ . ' executed for invalid userId (' . $this->params['userId'] . ')'
			);
		}
		$taskSuggester->suggest(
			$userIdentity,
			new TaskSetFilters(
				$this->newcomerTasksUserOptionsLookup->getTaskTypeFilter( $userIdentity ),
				$this->newcomerTasksUserOptionsLookup->getTopics( $userIdentity ),
				$this->newcomerTasksUserOptionsLookup->getTopicsMatchMode( $userIdentity )
			),
			SearchTaskSuggester::DEFAULT_LIMIT,
			null,
			[ 'useCache' => false ]
		);
		return true;
	}
}
