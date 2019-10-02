<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\TaskSet;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Service for getting task recommendations for inexperienced users.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
interface TaskSuggester {

	/**
	 * @param UserIdentity $user
	 * @param array|null $taskTypeFilter List of task types to limit the suggestions to.
	 *   When omitted, will be taken from the users' preferences.
	 * @param array|null $topicFilter List of topics to limit the suggestions to.
	 *   When omitted, will be taken from the users' preferences.
	 * @param int|null $limit Number of suggestions to return.
	 * @param int|null $offset Offset within full result set, for continuation.
	 * @return TaskSet|StatusValue A set of suggestions or an error in the form of a
	 *   StatusValue.
	 */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = null,
		array $topicFilter = null,
		$limit = null,
		$offset = null
	);

}
