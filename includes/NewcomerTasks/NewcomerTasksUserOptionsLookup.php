<?php

namespace GrowthExperiments\NewcomerTasks;

use Config;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\VariantHooks;
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

	/**
	 * @param ExperimentUserManager $experimentUserManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Config $config
	 */
	public function __construct(
		ExperimentUserManager $experimentUserManager,
		UserOptionsLookup $userOptionsLookup,
		Config $config
	) {
		$this->experimentUserManager = $experimentUserManager;
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
		$taskTypes = $this->getJsonListOption( $user, SuggestedEdits::TASKTYPES_PREF );
		// Filter out invalid task types for the user and use defaults based on user options.
		// This will be removed once A/B testing is over (T278123).
		if ( $taskTypes !== null ) {
			return $this->convertTaskTypes( $taskTypes, $user );
		} elseif ( $this->areLinkRecommendationsEnabled( $user ) ) {
			return [ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ];
		} else {
			return SuggestedEdits::DEFAULT_TASK_TYPES;
		}
	}

	/**
	 * Get the given user's topic preferences.
	 * @param UserIdentity $user
	 * @return string[] A list of topic IDs, or an empty array when the user has
	 *   no preference set (This is method is meant to be compatible with TaskSuggester
	 *   which takes an empty array as "no filtering".)
	 * @see \GrowthExperiments\NewcomerTasks\Topic\Topic::getId()
	 */
	public function getTopicFilter( UserIdentity $user ): array {
		return $this->getTopicFilterWithoutFallback( $user ) ?? [];
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
	 * This is a temporary hack that is expected to be removed when the link recommendation A/B
	 * test is over (T278123).
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function areLinkRecommendationsEnabled( UserIdentity $user ): bool {
		return $this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' )
			&& $this->config->get( 'GELinkRecommendationsFrontendEnabled' )
			&& $this->experimentUserManager->isUserInVariant( $user,
				VariantHooks::VARIANT_LINK_RECOMMENDATION_ENABLED );
	}

	/**
	 * Check if link recommendations are enabled. When true, the link-recommendation task type
	 * should be made available to the user.
	 * This is a temporary hack that is expected to be removed when the image recommendation A/B
	 * test is over.
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function areImageRecommendationsEnabled( UserIdentity $user ): bool {
		return $this->config->get( 'GENewcomerTasksImageRecommendationsEnabled' )
			&& $this->experimentUserManager->isUserInVariant( $user,
				VariantHooks::VARIANT_IMAGE_RECOMMENDATION_ENABLED );
	}

	/**
	 * Remove task types which the user is not supposed to see, given the link recommendation
	 * configuration.
	 * This is a hack that should be removed when the link recommendation A/B test is over (T278123).
	 * @param string[] $taskTypes Task types IDs.
	 * @param UserIdentity $user
	 * @return string[] Filtered task types IDs. Array keys are not preserved.
	 */
	public function filterTaskTypes( array $taskTypes, UserIdentity $user ): array {
		if ( $this->areLinkRecommendationsEnabled( $user ) ) {
			$taskTypes = array_diff( $taskTypes, [ 'links' ] );
		} else {
			$taskTypes = array_diff( $taskTypes, [ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ] );
		}
		return array_values( $taskTypes );
	}

	/**
	 * Convert task types which the user is not supposed to see, given the link recommendation
	 * configuration, to the closest task type available to them.
	 * This is a hack that should be removed when the link recommendation A/B test is over (T278123).
	 * @param string[] $taskTypes Task types IDs.
	 * @param UserIdentity $user
	 * @return string[] Converted task types IDs. Array keys are not preserved.
	 */
	private function convertTaskTypes( array $taskTypes, UserIdentity $user ): array {
		if ( $this->areLinkRecommendationsEnabled( $user ) ) {
			$map = [ 'links' => LinkRecommendationTaskTypeHandler::TASK_TYPE_ID ];
		} else {
			$map = [ LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => 'links' ];
		}
		$taskTypes = array_map( static function ( string $taskType ) use ( $map ) {
			return $map[$taskType] ?? $taskType;
		}, $taskTypes );
		return array_unique( $taskTypes );
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
