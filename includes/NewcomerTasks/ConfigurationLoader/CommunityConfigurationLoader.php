<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\Providers\SuggestedEditsConfigProvider;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use LogicException;
use MediaWiki\Json\FormatJson;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * Load configuration from the suggested edits provider of
 * CommunityConfiguration.
 *
 * For syntax on the task types see {@link SuggestedEditsSchema}.
 * https://cs.wikipedia.org/wiki/MediaWiki:GrowthExperimentsSuggestedEdits.json
 *
 */
class CommunityConfigurationLoader implements ConfigurationLoader {

	use ConfigurationLoaderTrait;

	private ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider;
	private TitleFactory $titleFactory;
	private LoggerInterface $logger;

	private TaskTypeHandlerRegistry $taskTypeHandlerRegistry;

	private ConfigurationValidator $configurationValidator;

	/** @var string[] */
	private array $enabledTaskTypeIds = [];

	/** @var ?callable */
	private $campaignConfigCallback;

	/** @var TaskType[]|null */
	private ?array $disabledTaskTypes = null;
	/** @var TaskType[]|StatusValue|null Cached task type set (or an error). */
	private $taskTypes;

	private array $disabledTaskTypeIds = [];

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		?SuggestedEditsConfigProvider $suggestedEditsConfigProvider,
		TitleFactory $titleFactory,
		LoggerInterface $logger
	) {
		$this->configurationValidator = $configurationValidator;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->suggestedEditsConfigProvider = $suggestedEditsConfigProvider;
		$this->titleFactory = $titleFactory;
		$this->logger = $logger;
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

	/**
	 * @param TaskType $taskType
	 * @return bool
	 */
	private function isDisabled( TaskType $taskType ) {
		return in_array( $taskType->getId(), $this->disabledTaskTypeIds, true );
	}
}
