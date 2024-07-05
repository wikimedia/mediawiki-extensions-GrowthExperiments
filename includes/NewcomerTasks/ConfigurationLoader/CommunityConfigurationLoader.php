<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\Providers\SuggestedEditsConfigProvider;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\Util;
use LogicException;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;

class CommunityConfigurationLoader extends AbstractDataConfigurationLoader {

	private ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider;
	private TitleFactory $titleFactory;
	private WikiPageConfigLoader $configLoader;

	/** @var LinkTarget|string|null */
	private $topicConfigurationPage;
	private LoggerInterface $logger;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param string $topicType
	 * @param ?SuggestedEditsConfigProvider $suggestedEditsConfigProvider
	 * @param TitleFactory $titleFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param string|LinkTarget|null $topicConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki). Can be omitted, in which case topic matching will be disabled.
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		string $topicType,
		?SuggestedEditsConfigProvider $suggestedEditsConfigProvider,
		TitleFactory $titleFactory,
		WikiPageConfigLoader $configLoader,
		$topicConfigurationPage,
		LoggerInterface $logger
	) {
		parent::__construct( $configurationValidator, $taskTypeHandlerRegistry, $topicType );

		$this->suggestedEditsConfigProvider = $suggestedEditsConfigProvider;
		$this->titleFactory = $titleFactory;
		$this->configLoader = $configLoader;
		$this->topicConfigurationPage = $topicConfigurationPage;
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
	 * @inheritDoc
	 */
	protected function loadTopicsConfig() {
		return $this->configLoader->load( $this->makeTitle( $this->topicConfigurationPage ) );
	}

	/**
	 * @inheritDoc
	 */
	public function loadTopics() {
		if ( $this->topicConfigurationPage === null ) {
			return [];
		}

		return parent::loadTopics();
	}
}
