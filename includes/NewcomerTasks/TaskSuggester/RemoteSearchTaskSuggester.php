<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use FauxSearchResultSet;
use GrowthExperiments\NewcomerTasks\FauxSearchResultWithScore;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Linker\LinkTarget;
use TitleFactory;

/**
 * Suggest edits based on searching a wiki via the API. Mainly meant for testing and
 * development; it can in theory be used in production but is less efficient than
 * using the search service internally.
 */
class RemoteSearchTaskSuggester extends SearchTaskSuggester {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var string Remote API URL including api.php */
	private $apiUrl;

	/**
	 * @param TemplateProvider $templateProvider
	 * @param SearchStrategy $searchStrategy
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param string $apiUrl Remote API URL including api.php
	 * @param TaskType[] $taskTypes
	 * @param Topic[] $topics
	 * @param LinkTarget[] $templateBlacklist
	 */
	public function __construct(
		TemplateProvider $templateProvider,
		SearchStrategy $searchStrategy,
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		$apiUrl,
		array $taskTypes,
		array $topics,
		array $templateBlacklist
	) {
		parent::__construct( $templateProvider, $searchStrategy, $taskTypes, $topics,
			$templateBlacklist );
		$this->requestFactory = $requestFactory;
		$this->titleFactory = $titleFactory;
		$this->apiUrl = $apiUrl;
	}

	/** @inheritDoc */
	protected function search(
		SearchQuery $query,
		int $limit,
		int $offset,
		bool $debug
	) {
		// We randomize the results so offsets are meaningless.
		// TODO use fixed random seed.
		$params = [
			'action' => 'query',
			'list' => 'search',
			'srsearch' => $query->getQueryString(),
			'srnamespace' => 0,
			'srlimit' => $limit,
			'srinfo' => 'totalhits',
			'srprop' => '',
			'srqiprofile' => 'classic_noboostlinks',
			// Convenient for debugging. Production setups should use LocalSearchTaskSuggester anyway.
			'errorlang' => 'en',
		];
		// FIXME quick fix: don't randomize if we use morelike, seems to conflict
		if ( $query->getSort() ) {
			$params['srsort'] = $query->getSort();
		}
		$status = Util::getApiUrl( $this->requestFactory, $this->apiUrl, $params );
		if ( !$status->isOK() ) {
			return $status;
		}
		$data = $status->getValue();

		$results = [];
		foreach ( $data['query']['search'] ?? [] as $i => $result ) {
			$title = $this->titleFactory->newFromText( $result['title'], $result['ns'] );
			// The search API does not expose scores :( Put in something fake, just to ease testing.
			$results[] = new FauxSearchResultWithScore( $title, 100 / ( $i + 1 ) );
		}
		$resultSet = new FauxSearchResultSet( $results, (int)$data['query']['searchinfo']['totalhits'] );

		if ( $debug ) {
			// Add Cirrus debug dump URLs which show the details of how the scores were calculated.
			$query->setDebugUrl( $this->apiUrl . '?' . wfArrayToCgi( $params, [
				'cirrusDumpResult' => 1,
				'cirrusExplain' => 'pretty',
			] ) );
		}

		return $resultSet;
	}

}
