<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\Providers\SuggestedEditsConfigProvider;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use MediaWiki\Json\FormatJson;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;

class CommunityConfigurationLoader extends AbstractDataConfigurationLoader {

	private ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider;
	private TitleFactory $titleFactory;
	private ?array $topicConfigData;
	private LoggerInterface $logger;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param ITopicRegistry $topicsRegistry
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
		ITopicRegistry $topicsRegistry,
		string $topicType,
		?SuggestedEditsConfigProvider $suggestedEditsConfigProvider,
		TitleFactory $titleFactory,
		?array $topicConfigData,
		LoggerInterface $logger
	) {
		parent::__construct( $configurationValidator, $taskTypeHandlerRegistry, $topicType, $topicsRegistry );

		$this->suggestedEditsConfigProvider = $suggestedEditsConfigProvider;
		$this->titleFactory = $titleFactory;
		$this->topicConfigData = $topicConfigData;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	protected function loadTaskTypesConfig() {
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
	 * @inheritDoc
	 */
	protected function loadTopicsConfig() {
		return $this->topicConfigData;
	}

	/**
	 * @inheritDoc
	 */
	public function loadTopics() {
		if ( $this->topicConfigData === null ) {
			return [];
		}

		return parent::loadTopics();
	}
}
