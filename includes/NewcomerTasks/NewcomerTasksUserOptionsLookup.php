<?php

namespace GrowthExperiments\NewcomerTasks;

use Config;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

/**
 * Retrieves user settings related to newcomer tasks.
 */
class NewcomerTasksUserOptionsLookup {

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var Config */
	private $config;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Config $config
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		Config $config
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->config = $config;
	}

	/**
	 * Get the given user's task type preferences.
	 * @param UserIdentity $user
	 * @return string[] A list of task type IDs, or the default task types when the user
	 * has no preference set.
	 * @see \GrowthExperiments\NewcomerTasks\TaskType\TaskType::getId()
	 */
	public function getTaskTypeFilter( UserIdentity $user ): array {
		return $this->getJsonListOption( $user, SuggestedEdits::TASKTYPES_PREF ) ??
			// FIXME: A follow-up commit will define this as a constant and export to
			// the client side.
			[ 'copyedit', 'links' ];
	}

	/**
	 * Get the given user's topic preferences.
	 * @param UserIdentity $user
	 * @return string[] A list of topic IDs, or an empty array when the user has
	 *   no preference set. (This is meant to be compatible with TaskSuggester which takes an
	 *   empty array as "no filtering".)
	 * @see \GrowthExperiments\NewcomerTasks\Topic\Topic::getId()
	 */
	public function getTopicFilter( UserIdentity $user ): array {
		return $this->getJsonListOption( $user, SuggestedEdits::getTopicFiltersPref( $this->config ) ) ?? [];
	}

	/**
	 * Read a user preference that is a list of strings.
	 * @param UserIdentity $user
	 * @param string $pref
	 * @return array|null User preferences as a list of strings, or null of the preference was
	 *   missing or invalid.
	 */
	private function getJsonListOption( UserIdentity $user, string $pref ) {
		$stored = $this->userOptionsLookup->getOption( $user, $pref );
		if ( $stored ) {
			$stored = json_decode( $stored, true );
		}
		// sanity check
		if ( !is_array( $stored ) || array_filter( $stored, 'is_string' ) !== $stored ) {
			return null;
		}
		return $stored;
	}

}
