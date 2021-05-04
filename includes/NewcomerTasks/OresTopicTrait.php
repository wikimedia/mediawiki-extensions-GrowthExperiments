<?php

namespace GrowthExperiments\NewcomerTasks;

use CirrusSearch\Query\ArticleTopicFeature;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoaderTrait;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\RawOresTopic;
use MediaWiki\MediaWikiServices;
use StatusValue;

/**
 * Functionality for searching for ORES topics instead of Growth topics.
 * This is a hack and should be replaced by TaskSuggester being more flexible
 * about ad-hoc topics / task types.
 * This implementation messes with the service container and should be used in maintenance
 * scripts only.
 */
trait OresTopicTrait {

	/**
	 * Use a replacement configuration loader with some extra features. Since this replaces
	 * a service, so it must be called before any Growth services have been accessed.
	 *
	 * @param bool $useOresTopics Use fake topic configuration consisting of raw ORES topics,
	 *   so we can use TaskSuggester to check the number of suggestions per ORES topic.
	 * @param TaskType[] $extraTaskTypes Extra task types to extend the task configuration with.
	 */
	protected function replaceConfigurationLoader(
		bool $useOresTopics,
		array $extraTaskTypes = []
	): void {
		$services = MediaWikiServices::getInstance();
		$services->addServiceManipulator( 'GrowthExperimentsNewcomerTasksConfigurationLoader',
			static function (
				ConfigurationLoader $configurationLoader,
				MediaWikiServices $services
			) use ( $useOresTopics, $extraTaskTypes ) {
				return new class (
					$configurationLoader,
					$useOresTopics,
					$extraTaskTypes
				) implements ConfigurationLoader {

					use ConfigurationLoaderTrait;

					/** @var ConfigurationLoader */
					private $realConfigurationLoader;

					/** @var TaskType[] */
					private $extraTaskTypes = [];

					/** @var RawOresTopic[]|null */
					private $topics;

					/**
					 * @param ConfigurationLoader $realConfigurationLoader
					 * @param bool $useOresTopics Use ORES topics instead of Growth topics
					 * @param TaskType[] $extraTaskTypes
					 */
					public function __construct(
						ConfigurationLoader $realConfigurationLoader,
						bool $useOresTopics,
						array $extraTaskTypes
					) {
						$this->realConfigurationLoader = $realConfigurationLoader;
						$this->extraTaskTypes = $extraTaskTypes;
						if ( $useOresTopics ) {
							$this->topics = array_map( static function ( string $oresId ) {
								return new RawOresTopic( $oresId, $oresId );
							}, array_keys( ArticleTopicFeature::TERMS_TO_LABELS ) );
						}
					}

					/** @inheritDoc */
					public function loadTaskTypes() {
						$taskTypes = $this->realConfigurationLoader->loadTaskTypes();
						if ( $taskTypes instanceof StatusValue ) {
							return $taskTypes;
						}
						return array_merge( $taskTypes, $this->extraTaskTypes );
					}

					/** @inheritDoc */
					public function loadTopics() {
						return $this->topics ?? $this->realConfigurationLoader->loadTopics();
					}

					/** @inheritDoc */
					public function loadExcludedTemplates() {
						return $this->realConfigurationLoader->loadExcludedTemplates();
					}

					/** @inheritDoc */
					public function loadExcludedCategories() {
						return $this->realConfigurationLoader->loadExcludedCategories();
					}
				};
			} );
	}

}
