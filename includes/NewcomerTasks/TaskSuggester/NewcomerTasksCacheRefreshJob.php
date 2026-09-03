<?php
declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use LogicException;
use MediaWiki\JobQueue\Job;
use MediaWiki\User\UserIdentityLookup;

/**
 * Refresh the newcomer tasks cache for a user.
 */
class NewcomerTasksCacheRefreshJob extends Job {

	public const JOB_NAME = 'newcomerTasksCacheRefreshJob';

	/** @inheritDoc */
	public function __construct(
		array $params,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly TaskSetFiltersFactory $taskSetFiltersFactory,
		private readonly TaskSuggesterFactory $taskSuggesterFactory
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->removeDuplicates = true;
	}

	/** @inheritDoc */
	public function run(): bool {
		$taskSuggester = $this->taskSuggesterFactory->create();
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $this->params['userId'] );
		if ( $userIdentity === null ) {
			throw new LogicException(
				__CLASS__ . ' executed for invalid userId (' . $this->params['userId'] . ')'
			);
		}
		$taskSuggester->suggest(
			$userIdentity,
			$this->taskSetFiltersFactory->newFromUser( $userIdentity ),
			SearchTaskSuggester::DEFAULT_LIMIT,
			null,
			[ 'useCache' => false ]
		);
		return true;
	}
}
