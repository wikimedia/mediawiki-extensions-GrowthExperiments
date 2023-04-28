<?php

namespace GrowthExperiments\NewcomerTasks;

use Config;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

/**
 * Retrieves user settings related to newcomer tasks.
 */
class NewcomerTasksUserOptionsLookup {

	/** @var ExperimentUserManager */
	private $experimentUserManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var Config */
	private $config;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/**
	 * @param ExperimentUserManager $experimentUserManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Config $config
	 * @param ConfigurationLoader $configurationLoader
	 */
	public function __construct(
		ExperimentUserManager $experimentUserManager,
		UserOptionsLookup $userOptionsLookup,
		Config $config,
		ConfigurationLoader $configurationLoader
	) {
		$this->experimentUserManager = $experimentUserManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->config = $config;
		$this->configurationLoader = $configurationLoader;
	}

	/**
	 * Get user's task types given their preferences and community configuration.
	 * @param UserIdentity $user
	 * @return string[] A list of task type IDs, or the default task types when the user
	 * has no preference set.
	 * @see \GrowthExperiments\NewcomerTasks\TaskType\TaskType::getId()
	 */
	public function getTaskTypeFilter( UserIdentity $user ): array {
		$taskTypes = $this->getJsonListOption( $user, SuggestedEdits::TASKTYPES_PREF );
		// Filter out invalid task types for the user and use defaults based on user options.
		if ( !$taskTypes ) {
			$taskTypes = $this->getDefaultTaskTypes();
		}
		return $this->filterNonExistentTaskTypes( $this->convertTaskTypes( $taskTypes ) );
	}

	/**
	 * Get the given user's topic preferences.
	 * @param UserIdentity $user
	 * @return string[] A list of topic IDs, or an empty array when the user has
	 *   no preference set (This is method is meant to be compatible with TaskSuggester
	 *   which takes an empty array as "no filtering".)
	 * @see \GrowthExperiments\NewcomerTasks\Topic\Topic::getId()
	 */
	public function getTopics( UserIdentity $user ): array {
		return $this->getTopicFilterWithoutFallback( $user ) ?? [];
	}

	/**
	 * Get the given user's topic mode preference.
	 * @param UserIdentity $user
	 * @return string A string representing the topic match mode.
	 * One of ( 'AND', 'OR').
	 * @see SearchStrategy::TOPIC_MATCH_MODES
	 */
	public function getTopicsMatchMode( UserIdentity $user ): string {
		$matchMode = $this->getStringOption( $user, SuggestedEdits::TOPICS_MATCH_MODE_PREF ) ??
			SearchStrategy::TOPIC_MATCH_MODE_OR;
		if ( $matchMode === SearchStrategy::TOPIC_MATCH_MODE_AND &&
			!$this->config->get( 'GETopicsMatchModeEnabled' ) ) {
			$matchMode = SearchStrategy::TOPIC_MATCH_MODE_OR;
		}
		return $matchMode;
	}

	/**
	 * Get the given user's topic preferences without a fallback to empty array.
	 * @param UserIdentity $user
	 * @return string[]|null A list of topic IDs, an empty array when the user has
	 *   no preference set, or null if preference wasn't set or is invalid.
	 */
	public function getTopicFilterWithoutFallback( UserIdentity $user ): ?array {
		return $this->getJsonListOption( $user, SuggestedEdits::getTopicFiltersPref( $this->config ) );
	}

	/**
	 * Check if link recommendations are enabled. When true, the link-recommendation task type
	 * should be made available to the user and the links task type hidden.
	 * @return bool
	 */
	public function areLinkRecommendationsEnabled(): bool {
		return $this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' )
			   && $this->config->get( 'GELinkRecommendationsFrontendEnabled' )
			   && array_key_exists( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				   $this->configurationLoader->getTaskTypes() );
	}

	/**
	 * Check if image recommendations are enabled. When true, the image-recommendation task type
	 * should be made available to the user.
	 * @return bool
	 */
	public function areImageRecommendationsEnabled(): bool {
		return $this->config->get( 'GENewcomerTasksImageRecommendationsEnabled' )
			&& array_key_exists( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
				$this->configurationLoader->getTaskTypes() );
	}

	/**
	 * Remove task types which the user is not supposed to see, given the link recommendation
	 * configuration and community configuration.
	 * @param string[] $taskTypes Task types IDs.
	 * @return string[] Filtered task types IDs. Array keys are not preserved.
	 */
	public function filterTaskTypes( array $taskTypes ): array {
		if ( $this->areLinkRecommendationsEnabled() ) {
			$taskTypes = array_diff( $taskTypes, [ 'links' ] );
		} else {
			$taskTypes = array_diff( $taskTypes, [ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ] );
		}
		if ( !$this->areImageRecommendationsEnabled() ) {
			$taskTypes = array_diff( $taskTypes, [ ImageRecommendationTaskTypeHandler::TASK_TYPE_ID ] );
		}
		return $this->filterNonExistentTaskTypes( $taskTypes );
	}

	/**
	 * Get default task types when the user has no stored preference.
	 * @return string[]
	 */
	private function getDefaultTaskTypes(): array {
		return SuggestedEdits::DEFAULT_TASK_TYPES;
	}

	/**
	 * Convert task types which the user is not supposed to see, given the link recommendation
	 * configuration, to the closest task type available to them.
	 * This is a hack that should be removed when A/B tests are over (T278123, T290403).
	 * @param string[] $taskTypes Task types IDs.
	 * @return string[] Converted task types IDs. Array keys are not preserved.
	 */
	private function convertTaskTypes( array $taskTypes ): array {
		if ( $this->areLinkRecommendationsEnabled() ) {
			$map = [ 'links' => LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ];
		} else {
			$map = [ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => 'links' ];
		}
		if ( !$this->areImageRecommendationsEnabled() ) {
			$map += [ ImageRecommendationTaskTypeHandler::TASK_TYPE_ID => false ];
		}

		$taskTypes = array_map( static function ( string $taskType ) use ( $map ) {
			return $map[$taskType] ?? $taskType;
		}, $taskTypes );
		return array_unique( array_filter( $taskTypes ) );
	}

	/**
	 * Remove task types which have been disabled via community configuration.
	 * @param string[] $taskTypesToFilter Task types IDs.
	 * @return string[] Filtered task types IDs.
	 */
	private function filterNonExistentTaskTypes( array $taskTypesToFilter ) {
		$allTaskTypes = $this->configurationLoader->getTaskTypes();
		return array_values( array_filter( $taskTypesToFilter,
			static function ( $taskTypeId ) use ( $allTaskTypes ) {
				return array_key_exists( $taskTypeId, $allTaskTypes );
			}
		) );
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

	/**
	 * Read a user preference that is a string.
	 * @param UserIdentity $user
	 * @param string $pref
	 * @return string|null User preference as a string, or null if the preference is invalid
	 */
	private function getStringOption( UserIdentity $user, string $pref ) {
		$stored = $this->userOptionsLookup->getOption( $user, $pref );

		if ( !is_string( $stored ) ) {
			return null;
		}
		return $stored;
	}

}
