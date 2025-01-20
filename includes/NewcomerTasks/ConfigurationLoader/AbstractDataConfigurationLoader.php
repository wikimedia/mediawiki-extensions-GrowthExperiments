<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Message\Message;
use StatusValue;

/**
 * Load configuration from a local or remote .json wiki page.
 * For syntax see
 * https://cs.wikipedia.org/wiki/MediaWiki:NewcomerTasks.json
 * https://cs.wikipedia.org/wiki/MediaWiki:NewcomerTopics.json
 * https://www.mediawiki.org/wiki/MediaWiki:NewcomerTopicsOres.json
 */
abstract class AbstractDataConfigurationLoader implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	/** @var string Use the configuration for OresBasedTopic topics. */
	public const CONFIGURATION_TYPE_ORES = 'ores';

	private const VALID_TOPIC_TYPES = [
		self::CONFIGURATION_TYPE_ORES,
	];

	/** @var TaskTypeHandlerRegistry */
	private TaskTypeHandlerRegistry $taskTypeHandlerRegistry;

	/** @var ConfigurationValidator */
	private ConfigurationValidator $configurationValidator;

	/** @var array */
	private array $disabledTaskTypeIds = [];
	/** @var string[] */
	private array $enabledTaskTypeIds = [];

	/** @var TaskType[]|StatusValue|null Cached task type set (or an error). */
	private $taskTypes;

	/** @var Topic[]|StatusValue|null Cached topic set (or an error). */
	private $topics;

	/**
	 * @var string One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	private string $topicType;

	/** @var ?callable */
	private $campaignConfigCallback;

	/** @var TaskType[]|null */
	private ?array $disabledTaskTypes = null;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param string $topicType One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		string $topicType
	) {
		$this->configurationValidator = $configurationValidator;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->topicType = $topicType;

		if ( !in_array( $this->topicType, self::VALID_TOPIC_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid topic type ' . $this->topicType );
		}
	}

	/**
	 * Hide the existence of the given task type. Must be called before task types are loaded.
	 * This is equivalent to setting the 'disabled' field in community configuration.
	 * @param string $taskTypeId
	 */
	public function disableTaskType( string $taskTypeId ): void {
		if ( $this->taskTypes !== null ) {
			throw new LogicException( __METHOD__ . ' must be called before task types are loaded' );
		}
		if ( !in_array( $taskTypeId, $this->disabledTaskTypeIds, true ) ) {
			$this->disabledTaskTypeIds[] = $taskTypeId;
		}
	}

	/**
	 * Force enabling of the given task type. Must be called before task types are loaded.
	 *
	 * This overrides configuration defined in Community configuration; apply care before usage.
	 * Intended for usage in maintenance scripts.
	 */
	public function enableTaskType( string $taskTypeId ): void {
		if ( $this->taskTypes !== null ) {
			throw new LogicException( __METHOD__ . ' must be called before task types are loaded' );
		}
		if ( !in_array( $taskTypeId, $this->enabledTaskTypeIds, true ) ) {
			$this->enabledTaskTypeIds[] = $taskTypeId;
		}
	}

	/**
	 * @return array|StatusValue
	 */
	abstract protected function loadTaskTypesConfig();

	/**
	 * @return array|StatusValue
	 */
	abstract protected function loadTopicsConfig();

	/**
	 * @return array|StatusValue
	 */
	abstract public function loadInfoboxTemplates();

	/** @inheritDoc */
	public function loadTaskTypes() {
		if ( $this->taskTypes !== null ) {
			return $this->taskTypes;
		}

		$config = $this->loadTaskTypesConfig();
		if ( $config instanceof StatusValue ) {
			$allTaskTypes = $config;
		} else {
			$allTaskTypes = $this->parseTaskTypesFromConfig( $config );
		}

		$this->disabledTaskTypeIds = array_diff( $this->disabledTaskTypeIds, $this->enabledTaskTypeIds );

		if ( !$allTaskTypes instanceof StatusValue ) {
			$taskTypes = array_filter( $allTaskTypes,
				fn ( TaskType $taskType ) => !$this->isDisabled( $taskType ) );
			$disabledTaskTypes = array_filter( $allTaskTypes,
				fn ( TaskType $taskType ) => $this->isDisabled( $taskType ) );
		} else {
			$taskTypes = $allTaskTypes;
			$disabledTaskTypes = [];
		}

		$this->taskTypes = $taskTypes;
		$this->disabledTaskTypes = array_combine( array_map( static function ( TaskType $taskType ) {
			return $taskType->getId();
		}, $disabledTaskTypes ), $disabledTaskTypes );
		return $this->taskTypes;
	}

	/** @inheritDoc */
	public function loadTopics() {
		if ( $this->topics !== null ) {
			return $this->topics;
		}

		$config = $this->loadTopicsConfig();
		if ( $config instanceof StatusValue ) {
			$topics = $config;
		} else {
			$topics = $this->parseTopicsFromConfig( $config );
		}

		$this->topics = $topics;
		return $topics;
	}

	/** @inheritDoc */
	public function getDisabledTaskTypes(): array {
		if ( $this->disabledTaskTypes === null ) {
			$this->loadTaskTypes();
		}
		return $this->disabledTaskTypes;
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
				$taskTypes[] = $taskType;
				if ( !empty( $taskTypeData['disabled'] ) ) {
					$this->disableTaskType( $taskTypeId );
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

	/**
	 * @param TaskType $taskType
	 * @return bool
	 */
	private function isDisabled( TaskType $taskType ) {
		return in_array( $taskType->getId(), $this->disabledTaskTypeIds, true );
	}

}
