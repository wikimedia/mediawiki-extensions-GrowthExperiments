<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\Config;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;

/**
 * Retrieves user settings related to newcomer tasks.
 */
class NewcomerTasksUserOptionsLookup {

	/**
	 * This property isn't used, but we want to preserve the ability to run A/B tests where
	 * user options depend on the user's experiment group.
	 * @var ExperimentUserManager
	 */
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
			$taskTypes = $this->getDefaultTaskTypes( $user );
		}
		return $this->filterNonExistentTaskTypes( $this->convertTaskTypes( $taskTypes, $user ) );
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
		return $this->getJsonListOption( $user, SuggestedEdits::TOPICS_ORES_PREF );
	}

	/**
	 * Check if link recommendations are enabled. When true, the link-recommendation task type
	 * should be made available to the user and the links task type hidden.
	 * @note This has to be equivalent to areLinkRecommendationsEnabled in TaskTypesAbFilter.js
	 * @return bool
	 */
	public function areLinkRecommendationsEnabled( UserIdentity $user ): bool {
		if ( $this->experimentUserManager->isUserInVariant( $user,
			VariantHooks::VARIANT_NO_LINK_RECOMMENDATION ) ) {
			// Disabled by a variant (T377787)
			return false;
		}

		return $this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' )
			   && $this->config->get( 'GELinkRecommendationsFrontendEnabled' )
			   && array_key_exists( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				   $this->configurationLoader->getTaskTypes() );
	}

	/**
	 * Check if image recommendations are enabled. When true, the image-recommendation task type
	 * should be made available to the user.
	 * @note This has to be equivalent to areImageRecommendationsEnabled in TaskTypesAbFilter.js
	 * @return bool
	 */
	public function areImageRecommendationsEnabled(): bool {
		return $this->config->get( 'GENewcomerTasksImageRecommendationsEnabled' )
			&& array_key_exists( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
				$this->configurationLoader->getTaskTypes() );
	}

	/**
	 * Check if section-level image recommendations are enabled. When true, the
	 * section-image-recommendation task type should be made available to the user.
	 * @note This has to be equivalent to areSectionImageRecommendationsEnabled in TaskTypesAbFilter.js
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function areSectionImageRecommendationsEnabled( UserIdentity $user ): bool {
		return $this->config->get( 'GENewcomerTasksSectionImageRecommendationsEnabled' )
			&& array_key_exists( SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
				$this->configurationLoader->getTaskTypes() );
	}

	/**
	 * Remove task types which the user is not supposed to see, given the link recommendation
	 * configuration and community configuration.
	 * @param string[] $taskTypes Task types IDs.
	 * @param UserIdentity $user
	 * @return string[] Filtered task types IDs. Array keys are not preserved.
	 */
	public function filterTaskTypes( array $taskTypes, UserIdentity $user ): array {
		$conversionMap = $this->getConversionMap( $user );
		$taskTypes = array_filter( $taskTypes,
			static fn ( $taskTypeId ) => !array_key_exists( $taskTypeId, $conversionMap )
		);
		return $this->filterNonExistentTaskTypes( $taskTypes );
	}

	/**
	 * Get default task types when the user has no stored preference.
	 * @param UserIdentity $user
	 * @return string[]
	 */
	private function getDefaultTaskTypes( UserIdentity $user ): array {
		// This doesn't do anything useful right now, but we want to preserve the ability
		// to determine the default task types dynamically for A/B testing.
		return SuggestedEdits::DEFAULT_TASK_TYPES;
	}

	/**
	 * Get mapping of task types which the user is not supposed to see to a similar task type
	 * or false (meaning nothing should be shown instead).
	 * Identical to TaskTypesAbFilter.getConversionMap().
	 * @param UserIdentity $user
	 * @return array A map of old task type ID => new task type ID or false.
	 * @phan-return array<string,string|false>
	 */
	private function getConversionMap( UserIdentity $user ): array {
		$map = [];
		if ( $this->areLinkRecommendationsEnabled( $user ) ) {
			$map += [ 'links' => LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ];
		} else {
			$map += [ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => 'links' ];
		}
		if ( !$this->areImageRecommendationsEnabled() ) {
			$map += [ ImageRecommendationTaskTypeHandler::TASK_TYPE_ID => false ];
		}
		if ( !$this->areSectionImageRecommendationsEnabled( $user ) ) {
			$map += [ SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID => false ];
		}
		return $map;
	}

	/**
	 * Convert task types which the user is not supposed to see, given the link recommendation
	 * configuration, to the closest task type available to them.
	 * @param string[] $taskTypes Task types IDs.
	 * @param UserIdentity $user
	 * @return string[] Converted task types IDs. Array keys are not preserved.
	 */
	public function convertTaskTypes( array $taskTypes, UserIdentity $user ): array {
		$map = $this->getConversionMap( $user );
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
