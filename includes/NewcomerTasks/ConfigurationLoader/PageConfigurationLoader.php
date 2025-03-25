<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\EmptyTopicRegistry;
use GrowthExperiments\Util;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleFactory;

class PageConfigurationLoader extends AbstractDataConfigurationLoader {

	private TitleFactory $titleFactory;
	private WikiPageConfigLoader $configLoader;

	/** @var LinkTarget|string */
	private $taskConfigurationPage;

	/** @var LinkTarget|string|null */
	private $topicConfigurationPage;

	private Config $growthConfig;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param string $topicType
	 * @param TitleFactory $titleFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param string|LinkTarget $taskConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki).
	 * @param string|LinkTarget|null $topicConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki). Can be omitted, in which case topic matching will be disabled.
	 * @param Config $growthConfig
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		string $topicType,
		TitleFactory $titleFactory,
		WikiPageConfigLoader $configLoader,
		$taskConfigurationPage,
		$topicConfigurationPage,
		Config $growthConfig
	) {
		parent::__construct( $configurationValidator, $taskTypeHandlerRegistry, $topicType, new EmptyTopicRegistry() );

		$this->titleFactory = $titleFactory;
		$this->configLoader = $configLoader;
		$this->taskConfigurationPage = $taskConfigurationPage;
		$this->topicConfigurationPage = $topicConfigurationPage;
		$this->growthConfig = $growthConfig;
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
	protected function loadTaskTypesConfig() {
		return $this->configLoader->load( $this->makeTitle( $this->taskConfigurationPage ) );
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
			// NOTE: This has to be here, rather than loadTopicsConfig(), as empty data does not
			// pass validation tests in the parent.
			return [];
		}

		return parent::loadTopics();
	}

	/**
	 * @inheritDoc
	 */
	public function loadInfoboxTemplates() {
		return $this->growthConfig->get( 'GEInfoboxTemplates' );
	}
}
