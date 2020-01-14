<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use ApiRawMessage;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
use SearchEngineFactory;
use StatusValue;

class LocalSearchTaskSuggester extends SearchTaskSuggester {

	/** @var TemplateProvider */
	private $templateProvider;

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	/**
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param TemplateProvider $templateProvider
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 * @param LinkTarget[] $templateBlacklist
	 */
	public function __construct(
		SearchEngineFactory $searchEngineFactory,
		TemplateProvider $templateProvider,
		array $taskTypes,
		array $topics,
		array $templateBlacklist
	) {
		parent::__construct( $templateProvider, $taskTypes, $topics, $templateBlacklist );
		$this->searchEngineFactory = $searchEngineFactory;
		$this->templateProvider = $templateProvider;
	}

	/**
	 * @inheritDoc
	 */
	protected function search( $searchTerm, $limit, $offset ) {
		$searchEngine = $this->searchEngineFactory->create();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setShowSuggestion( false );
		if ( in_array( 'random', $searchEngine->getValidSorts(), true ) ) {
			$searchEngine->setSort( 'random' );
		}
		$matches = $searchEngine->searchText( $searchTerm );
		if ( $matches instanceof StatusValue ) {
			if ( !$matches->isOK() ) {
				return $matches;
			} else {
				$matches = $matches->getValue();
			}
		}
		if ( !$matches ) {
			return StatusValue::newFatal( new ApiRawMessage(
				'Full text searches are unsupported or disabled',
				'grothexperiments-no-fulltext-search'
			) );
		}
		return $matches;
	}
}
