<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use CirrusSearch\Query\ArticleTopicFeature;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\RawOresTopic;
use StatusValue;

/**
 * Configuration loader for customizing topic types (ores or growth) and task types;
 * used in listTaskCounts maintenance script
 */
class TopicDecorator implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var bool */
	private $useOresTopics;

	/**
	 * @var TaskType[]
	 */
	private $extraTaskTypes;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param bool $useOresTopics Whether raw ORES topic should be used
	 * @param TaskType[] $extraTaskTypes Extra task types to extend the task configuration with.
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		bool $useOresTopics,
		array $extraTaskTypes = []
	) {
		$this->configurationLoader = $configurationLoader;
		$this->useOresTopics = $useOresTopics;
		$this->extraTaskTypes = $extraTaskTypes;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		$taskTypes = $this->configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $taskTypes;
		}
		return array_merge( $taskTypes, $this->extraTaskTypes );
	}

	/** @inheritDoc */
	public function loadTopics() {
		if ( $this->useOresTopics ) {
			$topics = array_map( static function ( string $oresId ) {
				return new RawOresTopic( $oresId, $oresId );
			}, array_keys( ArticleTopicFeature::TERMS_TO_LABELS ) );
			if ( $topics ) {
				return $topics;
			}
		}
		return $this->configurationLoader->loadTopics();
	}
}
