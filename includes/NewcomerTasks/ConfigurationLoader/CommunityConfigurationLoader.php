<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\Providers\SuggestedEditsConfigProvider;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\NewcomerTasks\Topic\WikimediaTopicRegistry;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Json\FormatJson;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * Load configuration from the suggested edits provider of
 * CommunityConfiguration. Also load topics configuration
 * from server.
 *
 * For syntax on the task types see:
 * GrowthExperiments\Config\Schemas\SuggestedEditsSchema.php
 * https://cs.wikipedia.org/wiki/MediaWiki:GrowthExperimentsSuggestedEdits.json
 *
 * For syntax on the topics see:
 * extension.json#config.GENewcomerTasksOresTopicConfig
 */
class CommunityConfigurationLoader implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	/** @var string Use the configuration for OresBasedTopic topics. */
	public const CONFIGURATION_TYPE_ORES = 'ores';

	private const VALID_TOPIC_TYPES = [
		self::CONFIGURATION_TYPE_ORES,
	];

	private ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider;
	private TitleFactory $titleFactory;
	private ?array $topicConfigData;
	private LoggerInterface $logger;

	private TaskTypeHandlerRegistry $taskTypeHandlerRegistry;

	private ConfigurationValidator $configurationValidator;

	/** @var string[] */
	private array $enabledTaskTypeIds = [];

	/** @var Topic[]|StatusValue|null Cached topic set (or an error). */
	private $topics;

	/**
	 * @var string One of the self::VALID_TOPIC_TYPES constants.
	 */
	private string $topicType;

	/** @var ?callable */
	private $campaignConfigCallback;

	/** @var TaskType[]|null */
	private ?array $disabledTaskTypes = null;
	private ITopicRegistry $topicRegistry;
	/** @var TaskType[]|StatusValue|null Cached task type set (or an error). */
	private $taskTypes;

	private array $disabledTaskTypeIds = [];

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ITopicRegistry $topicRegistry
	 * @param string $topicType
	 * @param ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider
	 * @param TitleFactory $titleFactory
	 * @param array|null $topicConfigData Configuration data for topic mapping. Can be
	 * omitted (set to null), in which case topic matching will be disabled.
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		ITopicRegistry $topicRegistry,
		string $topicType,
		?SuggestedEditsConfigProvider $suggestedEditsConfigProvider,
		TitleFactory $titleFactory,
		?array $topicConfigData,
		LoggerInterface $logger
	) {
		$this->configurationValidator = $configurationValidator;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->topicRegistry = $topicRegistry;
		$this->topicType = $topicType;
		$this->suggestedEditsConfigProvider = $suggestedEditsConfigProvider;
		$this->titleFactory = $titleFactory;
		$this->topicConfigData = $topicConfigData;
		$this->logger = $logger;

		if ( !in_array( $this->topicType, self::VALID_TOPIC_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid topic type ' . $this->topicType );
		}
	}

	/**
	 * Like task types configuration from provider
	 * @return array|StatusValue
	 */
	public function loadTaskTypesConfig() {
		if ( $this->suggestedEditsConfigProvider === null ) {
			$this->logger->debug( __METHOD__ . ': Suggested Edits config provider is null', [
				'exception' => new \RuntimeException,
			] );
			return [];
		}
		$result = $this->suggestedEditsConfigProvider->loadForNewcomerTasks();
		if ( $result->isOK() ) {
			// GrowthExperiments needs arrays, not stdClass...
			return FormatJson::decode(
				FormatJson::encode( $result->getValue() ),
				true
			);
		}
		return $result;
	}

	public function loadTaskTypes(): array {
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
	 * @inheritDoc
	 */
	public function getDisabledTaskTypes(): array {
		if ( $this->disabledTaskTypes === null ) {
			$this->loadTaskTypes();
		}
		return $this->disabledTaskTypes;
	}

	/**
	 * Hide the existence of the given task type. Must be called before task types are loaded.
	 * This is equivalent to setting the 'disabled' field in community configuration.
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
	 * @inheritDoc
	 */
	public function loadInfoboxTemplates() {
		if ( $this->suggestedEditsConfigProvider === null ) {
			$this->logger->debug( __METHOD__ . ': Suggested Edits config provider is null', [
				'exception' => new \RuntimeException
			] );
			return [];
		}
		$result = $this->suggestedEditsConfigProvider->loadValidConfiguration();
		if ( $result->isOK() ) {
			return $result->getValue()->{'GEInfoboxTemplates'};
		}
		return $result;
	}

	protected function loadTopicsConfig(): ?array {
		return $this->topicConfigData;
	}

	/** @inheritDoc */
	public function loadTopics() {
		if ( $this->topicConfigData === null ) {
			return [];
		}
		if ( $this->topics !== null ) {
			return $this->topics;
		}

		$topics = [];
		// T386018: Handle this more gracefully. Do not allow changing ORES-based topic definitions per wiki.
		if ( $this->topicRegistry instanceof WikimediaTopicRegistry ) {
			$topics = $this->parseTopicsFromConfig( $this->topicConfigData );
		}

		$this->topics = $topics;
		return $topics;
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

		$validORESTopics = $this->topicRegistry->getAllTopics();
		foreach ( $config as $topicId => $topicConfiguration ) {
			if ( !in_array( $topicId, $validORESTopics, true ) ) {
				// T386018: Handle this more gracefully. Do not allow changing ORES-based topic definitions via config.
				$status->fatal( new RawMessage( "'$topicId' is not a valid topic ID." ) );
			}
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
	 * Get campaign-specific topics
	 */
	private function getCampaignTopics(): array {
		if ( is_callable( $this->campaignConfigCallback ) ) {
			$getCampaignConfig = $this->campaignConfigCallback;
			return $getCampaignConfig()->getCampaignTopics();
		}
		return [];
	}

	/**
	 * Set the callback used to retrieve CampaignConfig, used to show campaign-specific topics
	 */
	public function setCampaignConfigCallback( callable $callback ) {
		$this->campaignConfigCallback = $callback;
	}

	/**
	 * @param TaskType $taskType
	 * @return bool
	 */
	private function isDisabled( TaskType $taskType ) {
		return in_array( $taskType->getId(), $this->disabledTaskTypeIds, true );
	}
}
