<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use ApiRawMessage;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Linker\LinkTarget;
use SearchEngine;
use SearchEngineFactory;
use SpecialPage;
use StatusValue;

class LocalSearchTaskSuggester extends SearchTaskSuggester {

	/** @var TemplateProvider */
	private $templateProvider;

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	/** @var string[] URLs with further CirrusSearch debug data */
	private $searchDebugUrls = [];

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
	protected function search( $taskType, $searchTerm, $limit, $offset, $debug ) {
		$searchEngine = $this->searchEngineFactory->create();
		$searchEngine->setLimitOffset( $limit, $offset );
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setShowSuggestion( false );
		$searchEngine->setFeatureData(
			SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE,
			'classic_noboostlinks'
		);
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
		if ( $debug ) {
			$this->searchDebugUrls[$taskType->getId()] = SpecialPage::getTitleFor( 'Search' )->getFullURL( [
				'search' => $searchTerm,
				'fulltext' => 1,
				'ns0' => 1,
				'limit' => $limit,
				'offset' => $offset,
			], false, PROTO_CANONICAL );
		}
		return $matches;
	}

	/** @inheritDoc */
	protected function setDebugData( TaskSet $taskSet ) : void {
		$taskSet->setDebugData( [ 'searchDebugUrls' => $this->searchDebugUrls ] );
	}

}
