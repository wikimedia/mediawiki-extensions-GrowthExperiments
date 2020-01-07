<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
use SearchEngineFactory;

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
		return $matches->isOK() ? $matches->getValue() : $matches;
	}
}
