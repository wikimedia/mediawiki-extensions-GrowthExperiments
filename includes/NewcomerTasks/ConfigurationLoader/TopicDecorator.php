<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use CirrusSearch\Query\ArticleTopicFeature;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\RawOresTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;

/**
 * Configuration loader for customizing topic types (ores or growth);
 * used in listTaskCounts maintenance script
 */
class TopicDecorator implements ConfigurationLoader, ITopicRegistry {

	use ConfigurationLoaderTrait;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var bool */
	private $useOresTopics;

	private ITopicRegistry $topicRegistry;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param ITopicRegistry $topicRegistry
	 * @param bool $useOresTopics Whether raw ORES topic should be used
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		ITopicRegistry $topicRegistry,
		bool $useOresTopics
	) {
		$this->configurationLoader = $configurationLoader;
		$this->useOresTopics = $useOresTopics;
		$this->topicRegistry = $topicRegistry;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		return $this->configurationLoader->loadTaskTypes();
	}

	private function loadTopics(): array {
		if ( $this->useOresTopics ) {
			$topics = array_map( static function ( string $oresId ) {
				return new RawOresTopic( $oresId, $oresId );
			}, array_keys( ArticleTopicFeature::TERMS_TO_LABELS ) );
			if ( $topics ) {
				return $topics;
			}
		}
		return $this->topicRegistry->getTopics();
	}

	/** @inheritDoc */
	public function getTopics(): array {
		$topics = $this->loadTopics();
		return array_combine( array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $topics ), $topics );
	}

	/** @inheritDoc */
	public function getTopicsMap(): array {
		return $this->getTopics();
	}

	/** @inheritDoc */
	public function getDisabledTaskTypes(): array {
		return $this->configurationLoader->getDisabledTaskTypes();
	}

	/** @inheritDoc */
	public function loadInfoboxTemplates(): array {
		return $this->configurationLoader->loadInfoboxTemplates();
	}
}
