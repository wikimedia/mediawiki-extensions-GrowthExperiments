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

class CommunityConfigurationLoader extends AbstractDataConfigurationLoader {

	private SuggestedEditsConfigProvider $suggestedEditsConfigProvider;
	private TitleFactory $titleFactory;
	private WikiPageConfigLoader $configLoader;

	/** @var LinkTarget|string|null */
	private $topicConfigurationPage;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param string $topicType
	 * @param SuggestedEditsConfigProvider $suggestedEditsConfigProvider
	 * @param TitleFactory $titleFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param string|LinkTarget|null $topicConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki). Can be omitted, in which case topic matching will be disabled.
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		string $topicType,
		SuggestedEditsConfigProvider $suggestedEditsConfigProvider,
		TitleFactory $titleFactory,
		WikiPageConfigLoader $configLoader,
		$topicConfigurationPage
	) {
		parent::__construct( $configurationValidator, $taskTypeHandlerRegistry, $topicType );

		$this->suggestedEditsConfigProvider = $suggestedEditsConfigProvider;
		$this->titleFactory = $titleFactory;
		$this->configLoader = $configLoader;
		$this->topicConfigurationPage = $topicConfigurationPage;
	}

	/**
	 * @inheritDoc
	 */
	protected function loadTaskTypesConfig() {
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
