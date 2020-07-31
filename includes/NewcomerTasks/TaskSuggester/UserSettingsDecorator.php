<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use Config;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

/**
 * A TaskSuggester decorator which uses user settings for task type and topic filter
 * when they are not explicitly provided.
 */
class UserSettingsDecorator implements TaskSuggester {

	/** @var TaskSuggester */
	private $taskSuggester;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var Config */
	private $config;

	/**
	 * @param TaskSuggester $taskSuggester
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Config $config
	 */
	public function __construct(
		TaskSuggester $taskSuggester,
		UserOptionsLookup $userOptionsLookup,
		Config $config
	) {
		$this->taskSuggester = $taskSuggester;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->config = $config;
	}

	/**
	 * @param string[]|null $taskTypeFilter List of task type IDs to limit the suggestions to.
	 *   When omitted, will be taken from the users' preferences. An empty array means no filtering.
	 * @param string[]|null $topicFilter List of topic IDs to limit the suggestions to.
	 *   When omitted, will be taken from the users' preferences. An empty array means no filtering.
	 * @inheritDoc
	 */
	public function suggest(
		UserIdentity $user,
		array $taskTypeFilter = null,
		array $topicFilter = null,
		$limit = null,
		$offset = null,
		$debug = false
	) {
		$taskTypeFilter = $taskTypeFilter ?? $this->getTaskTypeFilter( $user );
		$topicFilter = $topicFilter ?? $this->getTopicFilter( $user );
		return $this->taskSuggester->suggest( $user, $taskTypeFilter, $topicFilter, $limit, $offset, $debug );
	}

	private function getTaskTypeFilter( UserIdentity $user ) {
		$stored = $this->userOptionsLookup->getOption( $user, SuggestedEdits::TASKTYPES_PREF );
		return is_array( $stored ) ? $stored : [];
	}

	private function getTopicFilter( UserIdentity $user ) {
		$pref = SuggestedEdits::getTopicFiltersPref( $this->config );
		$stored = $this->userOptionsLookup->getOption( $user, $pref );
		return is_array( $stored ) ? $stored : [];
	}

}
