<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Service for getting task recommendations for inexperienced users.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
interface TaskSuggester {

	/**
	 * @param UserIdentity $user
	 * @param TaskSetFilters $taskSetFilters Filters to apply to the suggestions
	 * @param int|null $limit Number of suggestions to return.
	 * @param int|null $offset Offset within full result set, for continuation.
	 * @param array $options Associative array of options:
	 *   - useCache (bool, default true): enable/disable caching if the implementation has any.
	 *   - resetCache (bool, default false): ignore and replace the cached result. Overrides
	 *     useCache.
	 *   - revalidateCache (bool, default true): whether cached results should be revalidated
	 *     by filtering out tasks where the page changed in such a way that makes the task
	 *     inapplicable (e.g. in the case of a template-based task, the template was removed).
	 *     This is more accurate but slower. No effect when useCache or resetCache is used.
	 *   - debug (bool, default false): Debug mode. Depending on the implementation, might
	 *     result in filling TaskSet::getDebugData(). Might also disable optimizations such as
	 *     caching.
	 * @return TaskSet|StatusValue A set of suggestions or an error in the form of a
	 *   StatusValue.
	 */
	public function suggest(
		UserIdentity $user,
		TaskSetFilters $taskSetFilters,
		?int $limit = null,
		?int $offset = null,
		array $options = []
	);

	/**
	 * Remove elements of a taskset which are not valid tasks anymore.
	 * @param UserIdentity $user
	 * @param TaskSet $taskSet
	 * @return TaskSet|StatusValue A set of suggestions or an error in the form of a StatusValue.
	 * @note The interchangeability of TaskTypes/Topics and task type / topic IDs (via
	 *   ConfigurationLoader) is relied on in some places, so passing in TaskType / Topic objects
	 *   for filtering can be fragile. It is OK to use it as long the result is never shown to
	 *   a user and $user is not a real user, though.
	 */
	public function filter( UserIdentity $user, TaskSet $taskSet );

}
