<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\TemplateProvider;
use SearchEngineFactory;

class LocalSearchTaskSuggester extends SearchTaskSuggester {

	/** @var TemplateProvider */
	private $templateProvider;

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	/**
	 * @param SearchEngineFactory $searchEngineFactory
	 * @param TemplateProvider $templateProvider
	 * @param array $taskTypes
	 * @param array $templateBlacklist
	 */
	public function __construct(
		SearchEngineFactory $searchEngineFactory,
		TemplateProvider $templateProvider,
		array $taskTypes,
		array $templateBlacklist
	) {
		parent::__construct( $templateProvider, $taskTypes, $templateBlacklist );
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
