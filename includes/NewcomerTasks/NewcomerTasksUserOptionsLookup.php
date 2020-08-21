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
	 * @return string[]|null A list of task type IDs, or null when the user has no preference set.
	 * @see \GrowthExperiments\NewcomerTasks\TaskType\TaskType::getId()
	 */
	public function getTaskTypeFilter( UserIdentity $user ) {
		return $this->getJsonOption( $user, SuggestedEdits::TASKTYPES_PREF );
	}

	/**
	 * Get the given user's topic preferences.
	 * @param UserIdentity $user
	 * @return string[]|null A list of topic IDs, or null when the user has no preference set.
	 * @see \GrowthExperiments\NewcomerTasks\Topic\Topic::getId()
	 */
	public function getTopicFilter( UserIdentity $user ) {
		return $this->getJsonOption( $user, SuggestedEdits::getTopicFiltersPref( $this->config ) );
	}

	private function getJsonOption( UserIdentity $user, string $pref ) {
		$stored = $this->userOptionsLookup->getOption( $user, $pref );
		if ( $stored ) {
			$stored = json_decode( $stored, true );
		}
		// sanity check
		return is_array( $stored ) ? $stored : null;
	}

}
