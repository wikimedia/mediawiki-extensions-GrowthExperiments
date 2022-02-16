<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Linker\LinkTarget;
use Message;
use StatusValue;
use TitleFactory;
use TitleValue;

/**
 * Load configuration from a local or remote .json wiki page.
 * For syntax see
 * https://cs.wikipedia.org/wiki/MediaWiki:NewcomerTasks.json
 * https://cs.wikipedia.org/wiki/MediaWiki:NewcomerTopics.json
 * https://www.mediawiki.org/wiki/MediaWiki:NewcomerTopicsOres.json
 */
class PageConfigurationLoader implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	/** @var string Use the configuration for OresBasedTopic topics. */
	public const CONFIGURATION_TYPE_ORES = 'ores';
	/** @var string Use the configuration for MorelikeBasedTopic topics. */
	public const CONFIGURATION_TYPE_MORELIKE = 'morelike';

	private const VALID_TOPIC_TYPES = [
		self::CONFIGURATION_TYPE_ORES,
		self::CONFIGURATION_TYPE_MORELIKE,
	];

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageConfigLoader */
	private $configLoader;

	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;

	/** @var ConfigurationValidator */
	private $configurationValidator;

	/** @var LinkTarget */
	private $taskConfigurationPage;

	/** @var LinkTarget|null */
	private $topicConfigurationPage;

	/** @var array */
	private $disabledTaskTypes = [];

	/** @var TaskType[]|StatusValue|null Cached task type set (or an error). */
	private $taskTypes;

	/** @var Topic[]|StatusValue|null Cached topic set (or an error). */
	private $topics;

	/**
	 * @var string One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	private $topicType;

	/** @var ?callable */
	private $campaignConfigCallback;

	/**
	 * @param TitleFactory $titleFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param string|LinkTarget $taskConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki).
	 * @param string|LinkTarget|null $topicConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki). Can be omitted, in which case topic matching will be disabled.
	 * @param string $topicType One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		WikiPageConfigLoader $configLoader,
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		$taskConfigurationPage,
		$topicConfigurationPage,
		string $topicType
	) {
		$this->titleFactory = $titleFactory;
		$this->configLoader = $configLoader;
		$this->configurationValidator = $configurationValidator;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->taskConfigurationPage = $taskConfigurationPage;
		$this->topicConfigurationPage = $topicConfigurationPage;
		$this->topicType = $topicType;

		if ( !in_array( $this->topicType, self::VALID_TOPIC_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid topic type ' . $this->topicType );
		}
	}

	/**
	 * Hide the existence of the given task type. Must be called before task types are loaded.
	 * @param string $taskTypeId
	 */
	public function disableTaskType( string $taskTypeId ): void {
		if ( $this->taskTypes !== null ) {
			throw new LogicException( __METHOD__ . ' must be called before task types are loaded' );
		}
		$this->disabledTaskTypes[] = $taskTypeId;
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		if ( $this->taskTypes !== null ) {
			return $this->taskTypes;
		}

		$config = $this->configLoader->load( $this->makeTitle( $this->taskConfigurationPage ) );
		if ( $config instanceof StatusValue ) {
			$taskTypes = $config;
		} else {
			$taskTypes = $this->parseTaskTypesFromConfig( $config );
		}

		if ( !$taskTypes instanceof StatusValue ) {
			$taskTypes = array_filter( $taskTypes, function ( TaskType $taskType ) {
				return !in_array( $taskType->getId(), $this->disabledTaskTypes, true );
			} );
		}

		$this->taskTypes = $taskTypes;
		return $taskTypes;
	}

	/** @inheritDoc */
	public function loadTopics() {
		if ( !$this->topicConfigurationPage ) {
			return [];
		} elseif ( $this->topics !== null ) {
			return $this->topics;
		}

		$config = $this->configLoader->load( $this->makeTitle( $this->topicConfigurationPage ) );
		if ( $config instanceof StatusValue ) {
			$topics = $config;
		} else {
			$topics = $this->parseTopicsFromConfig( $config );
		}

		$this->topics = $topics;
		return $topics;
	}

	/**
	 * @param string|LinkTarget|null $target
	 * @return LinkTarget|null
	 */
	private function makeTitle( $target ) {
		if ( is_string( $target ) ) {
			$target = $this->titleFactory->newFromText( $target );
		}
		if ( $target && !$target->isExternal() && !$target->inNamespace( NS_MEDIAWIKI ) ) {
			Util::logException( new LogicException( 'Configuration page not in NS_MEDIAWIKI' ),
				[ 'title' => $target->__toString() ] );
		}
		return $target;
	}

	/**
	 * Like loadTaskTypes() but without caching.
	 * @param mixed $config A JSON value.
	 * @return TaskType[]|StatusValue
	 */
	private function parseTaskTypesFromConfig( $config ) {
		$status = StatusValue::newGood();
		$taskTypes = [];
		if ( !is_array( $config ) || array_filter( $config, 'is_array' ) !== $config ) {
			return StatusValue::newFatal(
				'growthexperiments-homepage-suggestededits-config-wrongstructure' );
		}
		foreach ( $config as $taskTypeId => $taskTypeData ) {
			// Fall back to legacy handler if not specified.
			$handlerId = $taskTypeData['type'] ?? TemplateBasedTaskTypeHandler::ID;
			if ( !$this->taskTypeHandlerRegistry->has( $handlerId ) ) {
				$status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidhandlerid',
					$taskTypeId, $handlerId, Message::listParam(
						$this->taskTypeHandlerRegistry->getKnownIds(), 'comma' ) );
				continue;
			}
			$taskTypeHandler = $this->taskTypeHandlerRegistry->get( $handlerId );
			$status->merge( $taskTypeHandler->validateTaskTypeConfiguration( $taskTypeId, $taskTypeData ) );

			if ( $status->isGood() ) {
				$taskType = $taskTypeHandler->createTaskType( $taskTypeId, $taskTypeData );
				$status->merge( $taskTypeHandler->validateTaskTypeObject( $taskType ) );
				// Leave the disabled check to the end; it seems nicer to validate entries
				// even when they are disabled.
				if ( empty( $taskTypeData['disabled'] ) ) {
					$taskTypes[] = $taskType;
				}
			}
		}
		return $status->isGood() ? $taskTypes : $status;
	}

	/**
	 * Like loadTopics() but without caching.
	 * @param mixed $config A JSON value.
	 * @return TaskType[]|StatusValue
	 */
	private function parseTopicsFromConfig( $config ) {
		$status = StatusValue::newGood();
		$topics = [];
		if ( !is_array( $config ) || array_filter( $config, 'is_array' ) !== $config ) {
			return StatusValue::newFatal(
				'growthexperiments-homepage-suggestededits-config-wrongstructure' );
		}

		$groups = [];
		if ( $this->topicType === self::CONFIGURATION_TYPE_ORES ) {
			if ( !isset( $config['topics'] ) || !isset( $config['groups'] ) ) {
				return StatusValue::newFatal(
					'growthexperiments-homepage-suggestededits-config-wrongstructure' );
			}
			$groups = $config['groups'];
			$config = $config['topics'];
		}

		foreach ( $config as $topicId => $topicConfiguration ) {
			$status->merge( $this->configurationValidator->validateIdentifier( $topicId ) );
			$requiredFields = [
				self::CONFIGURATION_TYPE_ORES => [ 'group', 'oresTopics' ],
				self::CONFIGURATION_TYPE_MORELIKE => [ 'label', 'titles' ],
			][$this->topicType];
			foreach ( $requiredFields as $field ) {
				if ( !isset( $topicConfiguration[$field] ) ) {
					$status->fatal( 'growthexperiments-homepage-suggestededits-config-missingfield',
						'titles', $topicId );
				}
			}

			if ( !$status->isGood() ) {
				// don't try to load if the config data format was invalid
				continue;
			}

			if ( $this->topicType === self::CONFIGURATION_TYPE_ORES ) {
				'@phan-var array{group:string,oresTopics:string[]} $topicConfiguration';
				$oresTopics = [];
				foreach ( $topicConfiguration['oresTopics'] as $oresTopic ) {
					$oresTopics[] = (string)$oresTopic;
				}
				$topic = new OresBasedTopic( $topicId, $topicConfiguration['group'], $oresTopics );
				$status->merge( $this->configurationValidator->validateTopicMessages( $topic ) );
			} elseif ( $this->topicType === self::CONFIGURATION_TYPE_MORELIKE ) {
				'@phan-var array{label:string,titles:string[]} $topicConfiguration';
				$linkTargets = [];
				foreach ( $topicConfiguration['titles'] as $title ) {
					$linkTargets[] = new TitleValue( NS_MAIN, $title );
				}
				$topic = new MorelikeBasedTopic( $topicId, $linkTargets );
				$topic->setName( $topicConfiguration['label'] );
			} else {
				throw new LogicException( 'Impossible but this makes phan happy.' );
			}
			$topics[] = $topic;
		}

		if ( $this->topicType === self::CONFIGURATION_TYPE_ORES && $status->isGood() ) {
			$this->configurationValidator->sortTopics( $topics, $groups );
		}

		// FIXME T301030 remove when campaign is done.
		$campaignTopics = array_map( static function ( $topic ) {
			return new CampaignTopic( $topic[ 'id' ], $topic[ 'searchExpression' ] );
		}, $this->getCampaignTopics() );
		if ( count( $campaignTopics ) ) {
			array_unshift( $topics, ...$campaignTopics );
		}

		return $status->isGood() ? $topics : $status;
	}

	/**
	 * Set the callback used to retrieve CampaignConfig, used to show campaign-specific topics
	 *
	 * @param callable $callback
	 */
	public function setCampaignConfigCallback( callable $callback ) {
		$this->campaignConfigCallback = $callback;
	}

	/**
	 * Get campaign-specific topics
	 *
	 * @return array
	 */
	private function getCampaignTopics(): array {
		if ( is_callable( $this->campaignConfigCallback ) ) {
			$getCampaignConfig = $this->campaignConfigCallback;
			return $getCampaignConfig()->getCampaignTopics();
		}
		return [];
	}
}
