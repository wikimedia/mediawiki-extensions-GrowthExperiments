<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Service for getting task recommendations for inexperienced users.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
interface TaskSuggester {

	/**
	 * @param UserIdentity $user
	 * @param string[] $taskTypeFilter List of task type IDs to limit the suggestions to.
	 *   An empty array means no filtering.
	 * @param string[] $topicFilter List of topic IDs to limit the suggestions to.
	 *   An empty array means no filtering.
	 * @param int|null $limit Number of suggestions to return.
	 * @param int|null $offset Offset within full result set, for continuation.
	 * @param array $options Associative array of options:
	 *   - useCache (bool, default true): enable/disable caching if the implementation has any.
	 *   - debug (bool, default false): Debug mode. Depending on the implementation, might
	 *     result in filling TaskSet::getDebugData(). Might also disable optimizations such as
	 *     caching.
	 * @return TaskSet|StatusValue A set of suggestions or an error in the form of a
	 *   StatusValue.
	 */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = [],
		array $topicFilter = [],
		?int $limit = null,
		?int $offset = null,
		array $options = []
	);

}
